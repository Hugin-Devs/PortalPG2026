<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent')->orderBy('order')->paginate(10);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $categories = Category::whereNull('parent_id')->get();

        return view('admin.categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'icon' => 'nullable|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = Str::slug($request->name);

        $category = Category::create($data);
        Audit::log('category.created', Category::class, $category->id, 'Categoría creada');

        return redirect('/admin/categorias')->with('success', 'Categoría creada correctamente.');
    }

    public function edit(Category $category)
    {
        $categories = Category::whereNull('parent_id')->where('id', '!=', $category->id)->get();

        return view('admin.categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'icon' => 'nullable|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['slug'] = Str::slug($request->name);
        $data['is_active'] = $request->has('is_active');

        $category->update($data);
        Audit::log('category.updated', Category::class, $category->id, 'Categoría actualizada');

        return redirect('/admin/categorias')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category)
    {
        if ($category->posts()->count() > 0) {
            return back()->with('error', 'No se puede eliminar una categoría con noticias asociadas.');
        }
        Audit::log('category.deleted', Category::class, $category->id, 'Categoría eliminada');
        $category->delete();

        return redirect('/admin/categorias')->with('success', 'Categoría eliminada correctamente.');
    }
}
