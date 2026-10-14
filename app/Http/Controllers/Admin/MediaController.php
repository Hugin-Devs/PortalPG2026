<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Webp;
use App\Support\Uploads;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index()
    {
        $media = Media::latest()->paginate(20);

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx|max:10240',
        ]);

        foreach ($request->file('files') as $file) {
            $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx']);
            $path = Uploads::storeDated($file, $filename);
            Webp::convert($path);

            Media::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'user_id' => auth()->id(),
            ]);
        }

        $count = count($request->file('files'));

        if ($request->expectsJson()) {
            return response()->json(['location' => asset('storage/'.$path)]);
        }

        $msg = $count === 1 ? 'Archivo subido correctamente.' : "{$count} archivos subidos correctamente.";

        return redirect('/admin/medios')->with('success', $msg);
    }

    public function destroy(Media $media)
    {
        \Storage::disk('public')->delete($media->path);
        Webp::delete($media->path);
        $media->delete();

        return redirect('/admin/medios')->with('success', 'Archivo eliminado correctamente.');
    }
}
