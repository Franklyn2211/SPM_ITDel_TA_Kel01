<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefCategory;
use App\Models\RefCategoryDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefCategoryDetailController extends Controller
{
    public function index()
    {
        $categoryDetails = RefCategoryDetail::paginate(10);
        $category = RefCategory::all();
        return view('admin.ref_category.detail', ['categoryDetails' => $categoryDetails, 'category' => $category]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'string', 'exists:ref_categories,id'],
            'name' => ['required', 'string', 'max:255', 'unique:ref_category_details,name'],
        ], [
            'name.unique' => 'Detail kategori dengan nama yang sama sudah terdaftar.',
        ]);

        $detail = new RefCategoryDetail([
            'id' => RefCategoryDetail::generateNextId(),
            'name' => $request->get('name'),
        ]);

        $category = RefCategory::findOrFail($request->get('category_id'));
        $detail->category()->associate($category);
        $detail->save();

        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail created successfully.');
    }

    public function update(Request $request, RefCategoryDetail $categoryDetail)
    {
        $request->validate([
            'category_id' => ['required', 'string', 'exists:ref_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ref_category_details', 'name')->ignore($categoryDetail->id, 'id'),
            ],
        ], [
            'name.unique' => 'Detail kategori dengan nama yang sama sudah terdaftar.',
        ]);

        if ($request->has('category_id')) {
            $category = RefCategory::findOrFail($request->get('category_id'));
            $categoryDetail->category()->associate($category);
        }

        $categoryDetail->update(['name' => $request->get('name')]);

        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail updated successfully.');
    }

    public function destroy($id)
    {
        $detail = RefCategoryDetail::findOrFail($id);
        $detail->delete();
        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail deleted successfully.');
    }
}
