<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefCategoryController extends Controller
{
    public function index()
    {
        $category = RefCategory::paginate(10);
        return view('admin.ref_category.index', ['category' => $category]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required','string','max:255','unique:ref_categories,name'],
        ], [
            'name.unique' => 'Kategori tersebut sudah terdaftar.',
        ]);

        $category = new RefCategory([
            'id'   => RefCategory::generateNextId(),
            'name' => $request->get('name'),
        ]);
        $category->save();

        return redirect()->route('admin.ref_category.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, RefCategory $category)
    {
        $request->validate([
            'name' => [
                'required','string','max:255',
                Rule::unique('ref_categories','name')->ignore($category->id, 'id'),
            ],
        ], [
            'name.unique' => 'Kategori dengan nama yang sama sudah terdaftar.',
        ]);

        $category->update(['name' => $request->get('name')]);

        return redirect()->route('admin.ref_category.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $category = RefCategory::findOrFail($id);
        $category->delete();

        return redirect()->route('admin.ref_category.index')
            ->with('success', 'Category deleted successfully.');
    }
}
