@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Importar desde Instagram</h1>
        <a href="{{ url('/admin/noticias') }}" class="btn btn-secondary">Volver</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $portal = url('/admin/noticias/importar');
        // Híbrido acotado al post actual:
        //  A) shortcode → solo scripts/JSON de ESTE post
        //  B) caption: h1 primero; og:description solo si no es stats
        //  C) TODAS las imágenes (fotos + cover de video) → image_urls
        //     (galería). Portada la elige el editor (featured_url/upload).
        //  D) Anti-avatar: -19/s640/profile_pic; fromJson prefiere
        //     display_url/thumbnail_src del media del post
        //  E) coverImage: en video/reel prioriza poster → og:image →
        //     JSON media (no imgs cualquiera del article)
        //  F) publishedAt: time[datetime] → article:published_time →
        //     taken_at_timestamp (hora de Caracas en el form)
        //  G) Next solo con indicadores de carrusel; about:blank + #ig={json}
        $js = <<<'JS'
javascript:(function(){
var u=location.href.split('?')[0].split('#')[0];
if(!/instagram\.com\/(p|reel|tv)\//.test(u)){alert('Abre un post de Instagram primero');return;}
var w=window.open('about:blank','_blank');
if(!w){alert('Permite las ventanas emergentes (popups) para Instagram y vuelve a intentar.');return;}
var P={{PORTAL}},urls=[];
var path=location.pathname||'';
var sm=path.match(/\/(p|reel|tv)\/([A-Za-z0-9_-]+)/);
var shortcode=sm?sm[2]:'';
function m(p){var e=document.querySelector('meta[property="'+p+'"]');return e?e.content:'';}
function mn(n){var e=document.querySelector('meta[name="'+n+'"]');return e?e.content:'';}
function mt(c){var e=document.querySelector('meta[name="twitter:'+c+'"]');return e?e.content:'';}
function key(s){return String(s||'').split('?')[0];}
function isJunkText(t){
  t=String(t||'').trim();
  if(!t)return false;
  if(/[\d.,]+\s*(posts?|publicaciones|followers?|seguidores|following|seguidos)/i.test(t))return true;
  if(/(posts?|publicaciones).*(followers?|seguidores).*(following|seguidos)/i.test(t))return true;
  if(/^(followers?|seguidores|following|seguidos|posts?|publicaciones)\b/i.test(t))return true;
  return false;
}
function isLikelyAvatarUrl(s){
  s=String(s||'').toLowerCase();
  if(!s)return false;
  if(s.indexOf('profile_pic')>-1||s.indexOf('profilepicture')>-1)return true;
  if(s.indexOf('t51.2885-19')>-1)return true;
  if(/\/s(150|160|320|480|640)x\1\//.test(s))return true;
  if(/\/r(100|150|320)x\1\//.test(s))return true;
  return false;
}
function isIgImageUrl(s){
  s=String(s||'');
  if(!/cdninstagram\.com|fbcdn\.net/.test(s))return false;
  return /\.(jpg|jpeg|png|webp)(\?|$)/i.test(key(s));
}
function cleanIgUrl(s){
  return String(s||'').replace(/\\\//g,'/').replace(/\\u0026/g,'&').replace(/^\/\//,'https://');
}
function isNotAvatar(im){
  if(!im)return false;
  var alt=(im.alt||'').toLowerCase();
  if(/profile|perfil|foto de perfil|profile picture/.test(alt))return false;
  var src=im.currentSrc||im.src||'';
  if(isLikelyAvatarUrl(src))return false;
  if(im.closest('header,nav,[role="banner"]'))return false;
  var r=im.getBoundingClientRect();
  if(r.width>0&&r.width<=150&&r.height>0&&r.height<=150)return false;
  if(im.naturalWidth>0&&im.naturalWidth<150)return false;
  return true;
}
function add(src){
  if(!src||urls.length>=11)return;
  if(!isIgImageUrl(src))return;
  if(isLikelyAvatarUrl(src))return;
  var k=key(src);
  for(var i=0;i<urls.length;i++){if(key(urls[i])===k)return;}
  urls.push(src);
}
function rootArticle(){
  var arts=document.querySelectorAll('article');
  for(var i=0;i<arts.length;i++){
    var a=arts[i];
    if(!shortcode)continue;
    if(a.innerHTML.indexOf(shortcode)>-1)return a;
  }
  for(var i=0;i<arts.length;i++){
    var a=arts[i];
    if(a.querySelector('video,img'))return a;
  }
  return document.querySelector('article')||document.querySelector('main')||document.body;
}
var root=null;
function isVideoPost(){
  if(root&&root.querySelector('video'))return true;
  return /\/(reel|tv)\//.test(path);
}
function collect(){
  root=root||rootArticle();
  if(!root)return;
  var minW=isVideoPost()?300:200;
  var list=root.querySelectorAll('img');
  for(var i=0;i<list.length;i++){
    var im=list[i];
    if(im.naturalWidth>0&&im.naturalWidth<minW)continue;
    if(!isNotAvatar(im))continue;
    add(im.currentSrc||im.src||'');
  }
  if(urls.length===0){
    var og=m('og:image');
    if(og)add(og);
  }
}
function shortcodeScripts(){
  var out=[];
  if(!shortcode)return out;
  var sc=document.querySelectorAll('script');
  for(var i=0;i<sc.length;i++){
    var t=sc[i].textContent||'';
    if(t.indexOf(shortcode)>-1&&(t.indexOf('cdninstagram')>-1||t.indexOf('fbcdn')>-1))out.push(t);
  }
  return out;
}
function fromJson(){
  try{
    var texts=shortcodeScripts();
    var mediaRe=/"(?:display_url|thumbnail_src)"\s*:\s*"([^"]+)"/g;
    var i,mx;
    for(i=0;i<texts.length&&urls.length<11;i++){
      mediaRe.lastIndex=0;
      while((mx=mediaRe.exec(texts[i]))&&urls.length<11){
        add(cleanIgUrl(mx[1]));
      }
    }
    if(urls.length===0){
      var re=/https?:\/\/[^"'\s\\]+(?:cdninstagram\.com|fbcdn\.net)\/[^"'\s\\]+\.(?:jpg|jpeg|png|webp)[^"'\s\\]*/gi;
      for(i=0;i<texts.length&&urls.length<11;i++){
        re.lastIndex=0;
        while((mx=re.exec(texts[i]))&&urls.length<11){
          add(cleanIgUrl(mx[0]));
        }
      }
    }
  }catch(e){}
}
function jsonMediaUrl(){
  try{
    var texts=shortcodeScripts();
    var re=/"(?:display_url|thumbnail_src)"\s*:\s*"([^"]+)"/g;
    for(var i=0;i<texts.length;i++){
      re.lastIndex=0;
      var mx=re.exec(texts[i]);
      if(mx){
        var s=cleanIgUrl(mx[1]);
        if(isIgImageUrl(s)&&!isLikelyAvatarUrl(s))return s;
      }
    }
  }catch(e){}
  return '';
}
function captionText(){
  root=root||rootArticle();
  if(root){
    var h1=root.querySelector('h1');
    if(h1){
      var ht=(h1.textContent||'').trim();
      if(ht.length>2&&!isJunkText(ht))return ht;
    }
  }
  var og=(m('og:description')||'').trim();
  if(og&&!isJunkText(og))return og;
  var md=(mn('description')||'').trim();
  if(md&&!isJunkText(md))return md;
  if(!root)return '';
  var sp=root.querySelectorAll('span[dir="auto"], h2');
  for(var i=0;i<sp.length;i++){
    var t=(sp[i].textContent||'').trim();
    if(t.length>2&&!isJunkText(t))return t;
  }
  var time=root.querySelector('time');
  if(time&&time.parentElement){
    var sib=time.parentElement.textContent||'';
    sib=sib.replace(/\s+/g,' ').trim();
    if(sib.length>2&&!isJunkText(sib))return sib;
  }
  return '';
}
function publishedAt(){
  root=root||rootArticle();
  if(root){
    var t=root.querySelector('time[datetime]');
    if(t){
      var dt=t.getAttribute('datetime');
      if(dt)return dt;
    }
  }
  var ap=m('article:published_time');
  if(ap)return ap;
  try{
    var texts=shortcodeScripts();
    for(var i=0;i<texts.length;i++){
      var mm=texts[i].match(/"taken_at_timestamp"\s*:\s*(\d{10})/);
      if(mm)return new Date(parseInt(mm[1],10)*1000).toISOString();
    }
  }catch(e){}
  return '';
}
function coverImage(){
  root=root||rootArticle();
  if(root){
    var v=root.querySelector('video');
    var poster=v?(v.poster||v.getAttribute('poster')||''):'';
    if(poster&&isIgImageUrl(poster)&&!isLikelyAvatarUrl(poster))return poster;
  }
  if(isVideoPost()){
    var og=m('og:image');
    if(og&&isIgImageUrl(og)&&!isLikelyAvatarUrl(og))return og;
    var tw=mt('image');
    if(tw&&isIgImageUrl(tw)&&!isLikelyAvatarUrl(tw))return tw;
    var pr=document.querySelector('link[rel="preload"][as="image"]');
    if(pr&&pr.href&&isIgImageUrl(pr.href)&&!isLikelyAvatarUrl(pr.href))return pr.href;
    var jm=jsonMediaUrl();
    if(jm)return jm;
    if(root){
      var vl=root.querySelectorAll('img');
      var vb='',vw=0;
      for(var i=0;i<vl.length;i++){
        var vim=vl[i];
        if(!isNotAvatar(vim))continue;
        var w=Math.max(vim.naturalWidth||0,vim.width||0);
        if(w>=300&&w>vw){vw=w;vb=vim.currentSrc||vim.src||'';}
      }
      if(vb)return vb;
    }
    return '';
  }
  if(root){
    var list=root.querySelectorAll('img');
    var best='',bestW=0;
    for(var i=0;i<list.length;i++){
      var im=list[i];
      if(!isNotAvatar(im))continue;
      var w=Math.max(im.naturalWidth||0,im.width||0);
      if(w>=300&&w>bestW){
        bestW=w;
        best=im.currentSrc||im.src||'';
      }
    }
    if(best)return best;
  }
  var og2=m('og:image');
  if(og2&&isIgImageUrl(og2)&&!isLikelyAvatarUrl(og2))return og2;
  var tw2=mt('image');
  if(tw2&&isIgImageUrl(tw2)&&!isLikelyAvatarUrl(tw2))return tw2;
  var pr2=document.querySelector('link[rel="preload"][as="image"]');
  if(pr2&&pr2.href&&isIgImageUrl(pr2.href)&&!isLikelyAvatarUrl(pr2.href))return pr2.href;
  if(root){
    var list2=root.querySelectorAll('img');
    for(var i=0;i<list2.length;i++){
      var s=list2[i].currentSrc||list2[i].src||'';
      if(s&&isNotAvatar(list2[i])&&isIgImageUrl(s))return s;
    }
  }
  return '';
}
function firstLine(s){
  s=String(s||'').trim();
  if(!s)return '';
  var nl=s.indexOf('\n');
  var line=nl>-1?s.slice(0,nl):s;
  line=line.replace(/\s+/g,' ').trim();
  if(line.length>180)line=line.slice(0,177)+'...';
  return line;
}
function hasCarouselIndicators(){
  root=root||rootArticle();
  if(!root)return false;
  if(root.querySelector('[aria-label*="lide"],[aria-label*="dot"],[role="tablist"]'))return true;
  var dots=root.querySelectorAll('button,li,span');
  var small=0;
  for(var i=0;i<dots.length&&i<40;i++){
    var el=dots[i];
    var r=el.getBoundingClientRect();
    if(r.width>0&&r.width<=14&&r.height<=14)small++;
  }
  if(small>=2)return true;
  var t=(root.textContent||'');
  if(/\b[1-9]\s*\/\s*[2-9]\b/.test(t))return true;
  return false;
}
function findNext(){
  root=root||rootArticle();
  if(!root||!hasCarouselIndicators())return null;
  var c=root.querySelectorAll('[aria-label="Next"],[aria-label="Siguiente"],[aria-label="Next Photo"],[aria-label="Next photo"],[aria-label="Siguiente foto"],[aria-label="Siguiente Foto"]');
  for(var i=0;i<c.length;i++){
    var el=c[i];
    if(el.offsetParent!==null||el.getClientRects().length>0)return el;
  }
  return null;
}
function status(msg){try{var el=document.getElementById('portal-ig-status');if(!el){el=document.createElement('div');el.id='portal-ig-status';el.style.cssText='position:fixed;bottom:16px;right:16px;z-index:2147483647;background:#111;color:#fff;padding:10px 14px;border-radius:8px;font:14px system-ui,sans-serif;';document.body.appendChild(el);}el.textContent=msg;}catch(e){}}
function clearStatus(){try{var el=document.getElementById('portal-ig-status');if(el&&el.parentNode)el.parentNode.removeChild(el);}catch(e){}}
function finish(){
  clearStatus();
  var content=captionText();
  var title=firstLine(content);
  if(!title||isJunkText(title)){
    var ogT=(m('og:title')||'').trim();
    title=(ogT&&!isJunkText(ogT))?ogT:'';
  }
  if(!title)title=document.title;
  var cover=coverImage();
  if(cover){
    var ck=key(cover);
    var rest=urls.filter(function(x){return key(x)!==ck;});
    urls=[cover].concat(rest);
  }
  var d={source_url:u,title:title,content:content,published_at:publishedAt(),image_urls:urls.slice(0,11)};
  var t=P+'#ig='+encodeURIComponent(JSON.stringify(d));
  try{w.location.href=t;}catch(e){location.href=t;}
}
var tries=0;
function step(){
  tries++;collect();
  if(urls.length>=11||tries>15){finish();return;}
  var n=findNext();
  if(!n){finish();return;}
  status('Portal IG: recopilando… '+urls.length+'/11');
  try{n.click();}catch(e){finish();return;}
  setTimeout(function(){collect();step();},500);
}
collect();fromJson();
if(urls.length>=11){finish();return;}
if(!findNext()){
  if(urls.length<=1){status('Portal IG: recopilando…');setTimeout(function(){collect();finish();},400);}
  else{finish();}
  return;
}
status('Portal IG: recopilando… '+urls.length+'/11');
setTimeout(step,300);
})();
JS;
        // Una sola línea: los javascript: multilinea se rompen al guardar favorito.
        // Primero se eliminan comentarios de línea completos (//…) — si no,
        // al unir líneas comentarían el resto del script (SyntaxError silencioso).
        $js = preg_replace('#^\s*//.*$#m', '', $js);
        $bookmarklet = str_replace(
            '{{PORTAL}}',
            json_encode($portal),
            preg_replace('/\s*\n\s*/', '', $js)
        );
    @endphp

    <div class="card mb-4">
        <div class="card-header">Método recomendado — bookmarklet</div>
        <div class="card-body">
            <ol class="mb-3">
                <li>Arrastra el botón <strong>“→ Portal IG”</strong> a tu barra de favoritos (o cópialo con el botón).</li>
                <li>Navega hasta el post de la gobernación o del gobernador en Instagram.</li>
                <li>Haz clic en el bookmarklet: se abrirá <strong>una pestaña nueva</strong> (Instagram queda abierta). Si es un carrusel, verás el aviso <em>“Portal IG: recopilando…”</em> mientras recorre las fotos (~1–3 s).</li>
                <li>Elige categoría, revisa título/extracto/contenido, <strong>fecha de publicación</strong>, <strong>selecciona la portada</strong> (o déjala vacía) e importa como <strong>borrador</strong>.</li>
            </ol>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ $bookmarklet }}" class="btn btn-primary" id="bookmarkletLink" title="Arrastra a tu barra de favoritos">→ Portal IG</a>
                <button type="button" class="btn btn-outline-secondary" id="copyBookmarklet">Copiar bookmarklet</button>
                <span id="copyFeedback" class="form-text" style="display:none;">Copiado al portapapeles.</span>
            </div>
            <div class="form-text mt-2">
                Permite <strong>popups</strong> en instagram.com si el navegador bloquea la pestaña nueva.
                En móviles la app de Instagram no ejecuta bookmarklets: copia la URL del post e importa desde este formulario en desktop.
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Formulario</div>
        <div class="card-body">
            <form action="{{ url('/admin/noticias/importar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label>URL del post de Instagram</label>
                    <input type="url" name="source_url" class="form-control" required
                           placeholder="https://www.instagram.com/p/..."
                           value="{{ old('source_url') }}">
                    <div class="form-text">Se rellena solo con el bookmarklet; también puedes pegarla a mano.</div>
                    @error('source_url') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Título</label>
                    <input type="text" name="title" class="form-control" required maxlength="255"
                           value="{{ old('title') }}">
                    @error('title') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Categoría</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">— Selecciona —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Extracto</label>
                    <textarea name="excerpt" class="form-control" rows="2" maxlength="1000">{{ old('excerpt') }}</textarea>
                    <div class="form-text">Vacío = se deriva del contenido (máx. 1000).</div>
                    @error('excerpt') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Contenido</label>
                    <textarea name="content" class="form-control" rows="8" required>{{ old('content') }}</textarea>
                    <div class="form-text">Texto del post. Las imágenes del texto se eliminan al guardar; usa portada/galería. Después puedes pulirlo con el editor completo.</div>
                    @error('content') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Fecha y hora de publicación (Instagram)</label>
                    <input type="datetime-local" name="published_at" class="form-control"
                           value="{{ old('published_at') }}">
                    <div class="form-text">Se rellena desde el bookmarklet (hora de Caracas). Al aprobar se conserva esta fecha. Vacío = al aprobar se pone “ahora”.</div>
                    @error('published_at') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <input type="hidden" name="image_urls" id="imageUrlsField" value="{{ old('image_urls') }}">

                <div class="mb-3">
                    <label>Imágenes detectadas</label>
                    <div id="carouselInfo" class="alert alert-secondary py-2" style="{{ old('image_urls') ? '' : 'display:none;' }}">
                        <span id="carouselCount"></span>
                    </div>
                    <div id="featuredPick" class="mt-2" style="display:none;">
                        <div class="form-text fw-bold mb-1">Portada (opcional):</div>
                        <div id="featuredPickList">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="featured_url" id="featuredNone" value="" checked>
                                <label class="form-check-label" for="featuredNone">Ninguna (todas a galería)</label>
                            </div>
                        </div>
                        <div class="form-text">Elige una imagen para la portada; el resto va a la galería. También puedes subir un archivo abajo (tiene prioridad).</div>
                    </div>
                    <div class="form-text mt-2">Las imágenes se descargan del CDN de Instagram al importar (allowlist).</div>
                    @error('image_urls') <div class="text-danger">{{ $message }}</div> @enderror
                    @error('image_urls.*') <div class="text-danger">{{ $message }}</div> @enderror
                    @error('featured_url') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label>Subir portada (opcional, tiene prioridad)</label>
                    <input type="file" name="featured_image_file" class="form-control"
                           accept="image/png,image/jpeg,image/gif,image/webp">
                    <div class="form-text">Si subes un archivo, se usa como portada y TODAS las imágenes detectadas van a la galería. JPG/PNG/GIF/WebP · máx. 10 MB.</div>
                    @error('featured_image_file') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="alert alert-info">
                    La importación crea siempre un <strong>borrador</strong> (<code>source=instagram</code>).
                    Aprueba desde el listado cuando la noticia esté lista.
                </div>

                <button type="submit" class="btn btn-primary">Importar como borrador</button>
            </form>
        </div>
    </div>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.addEventListener('DOMContentLoaded', function () {
    var bookmarklet = @json($bookmarklet);

    var copyBtn = document.getElementById('copyBookmarklet');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var done = function () {
                var fb = document.getElementById('copyFeedback');
                if (fb) {
                    fb.style.display = '';
                    setTimeout(function () { fb.style.display = 'none'; }, 2500);
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(bookmarklet).then(done);
            } else {
                window.prompt('Copia el bookmarklet:', bookmarklet);
            }
        });
    }

    function fillFromHash() {
        if (location.hash.indexOf('#ig=') !== 0) {
            return;
        }
        try {
            var raw = location.hash.slice(4);
            var data = JSON.parse(decodeURIComponent(raw));
            if (data.source_url) {
                document.querySelector('[name="source_url"]').value = data.source_url;
            }
            if (data.title) {
                document.querySelector('[name="title"]').value = data.title;
            }
            if (data.content) {
                document.querySelector('[name="content"]').value = data.content;
            }
            if (data.published_at) {
                var pa = document.querySelector('[name="published_at"]');
                if (pa) {
                    try {
                        var pd = new Date(data.published_at);
                        if (!isNaN(pd.getTime())) {
                            pa.value = pd.toLocaleString('sv-SE', { timeZone: 'America/Caracas' })
                                .replace(' ', 'T').slice(0, 16);
                        }
                    } catch (e) {
                        pa.value = String(data.published_at).replace(/\.\d+Z$/, '').replace(/Z$/, '').slice(0, 16);
                    }
                }
            }
            if (data.image_urls && data.image_urls.length) {
                document.getElementById('imageUrlsField').value = JSON.stringify(data.image_urls);
                var n = data.image_urls.length;
                var info = document.getElementById('carouselInfo');
                document.getElementById('carouselCount').textContent =
                    n + ' imagen(es) detectada(s) → galería. Elige cuál va a portada (o ninguna).';
                info.style.display = '';

                var list = document.getElementById('featuredPickList');
                data.image_urls.forEach(function (url, i) {
                    var id = 'featuredImg' + i;
                    var row = document.createElement('div');
                    row.className = 'form-check';
                    var input = document.createElement('input');
                    input.className = 'form-check-input';
                    input.type = 'radio';
                    input.name = 'featured_url';
                    input.id = id;
                    input.value = url;
                    var label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.setAttribute('for', id);
                    label.textContent = 'Imagen ' + (i + 1) + ' — ' + url.replace(/^https?:\/\//, '').slice(0, 60) + (url.length > 60 ? '…' : '');
                    row.appendChild(input);
                    row.appendChild(label);
                    list.appendChild(row);
                });
                document.getElementById('featuredPick').style.display = '';
            }
            history.replaceState(null, '', location.pathname + location.search);
        } catch (e) {
            // Hash corrupto: no bloquear el form.
        }
    }

    fillFromHash();
});
</script>
@endsection
