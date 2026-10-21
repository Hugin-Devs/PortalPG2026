<?php

/*
|--------------------------------------------------------------------------
| Configuración de HTMLPurifier (fuente única)
|--------------------------------------------------------------------------
|
| El trait App\Support\SanitizesHtml lee estos valores tanto al escribir
| (setContentAttribute) como al leer (rendered_content), de modo que la
| allowlist vive solo aquí y no puede divergir entre capas.
|
*/

return [

    /*
    | Etiquetas y atributos permitidos tras la sanitización (alineado con
    | TinyMCE). Sin `img`: las imágenes se gestionan con portada y galería.
    */
    'allowed' => 'p,br,b,strong,i,em,u,a[href|title],'
        .'h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,pre,code,'
        .'table,thead,tbody,tr,th,td,span[class],div[class]',

    /*
    | Esquemas de URI permitidos. Sin `data:`: evitaría `<a href="data:...">`.
    */
    'allowed_schemes' => [
        'http' => true,
        'https' => true,
        'mailto' => true,
        'tel' => true,
    ],
];
