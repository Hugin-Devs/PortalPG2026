<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    public function index()
    {
        $videos = Video::latest()->get();

        return view('admin.videos.index', compact('videos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(['youtube', 'file'])],
            'url' => ['required_if:type,youtube', 'nullable', 'string', 'max:500', 'regex:~^(https?://)?(www\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/)[A-Za-z0-9_-]{11}(&.*)?$~'],
            'file' => ['required_if:type,file', 'nullable', 'file', 'mimes:mp4,webm,ogg', 'max:102400'],
        ], [
            'url.regex' => 'La URL no parece un video de YouTube válida.',
            'url.required_if' => 'Ingresa la URL del video de YouTube.',
            'file.required_if' => 'Selecciona un archivo de video.',
        ]);

        $data = [
            'title' => $request->title,
            'type' => $request->type,
            'url' => null,
            'path' => null,
            'user_id' => auth()->id(),
        ];

        if ($request->type === 'youtube') {
            $data['url'] = $request->url;
        } else {
            $file = $request->file('file');
            $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['mp4', 'webm', 'ogg']);
            $data['path'] = $file->storeAs('videos', $filename, 'public');
        }

        Video::create($data);

        return redirect('/admin/videos#tab-cargar')->with('success', 'Video cargado correctamente.');
    }

    public function destacar(Request $request)
    {
        $request->validate([
            'video_id' => ['nullable', 'integer', Rule::exists('videos', 'id')],
        ]);

        DB::table('videos')->update(['is_featured' => false]);

        if ($request->filled('video_id')) {
            DB::table('videos')->where('id', $request->video_id)->update(['is_featured' => true]);

            return redirect('/admin/videos#tab-portada')->with('success', 'Video marcado para la portada.');
        }

        return redirect('/admin/videos#tab-portada')->with('success', 'Ningún video en la portada.');
    }

    public function destroy(Video $video)
    {
        if ($video->path) {
            \Storage::disk('public')->delete($video->path);
        }
        $video->delete();

        return redirect('/admin/videos#tab-cargar')->with('success', 'Video eliminado correctamente.');
    }
}
