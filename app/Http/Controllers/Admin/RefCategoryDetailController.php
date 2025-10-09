<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefCategory;
use App\Models\RefCategoryDetail;
use Illuminate\Http\Request;

class RefCategoryDetailController extends Controller
{
    public function index()
    {
        $categoryDetails = RefCategoryDetail::all();
        $category = RefCategory::all();
        return view('admin.ref_category.detail', ['categoryDetails' => $categoryDetails, 'category' => $category]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $categoryDetails = new RefCategoryDetail([
            'id' => RefCategoryDetail::generateNextId(),
            'name' => $request->get('name'),
        ]);

        $category = RefCategory::findOrFail($request->get('category_id'));
        $categoryDetails->category()->associate($category);

        $categoryDetails->save();
        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail created successfully.');
    }

    public function update(Request $request, RefCategoryDetail $categoryDetail)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $data = [
            'name' => $request->get('name'),
        ];

        if ($request->has('category_id')) {
            $category = RefCategory::findOrFail($request->get('category_id'));
            $categoryDetail->category()->associate($category);
        }

        $categoryDetail->update($data);

        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail updated successfully.');
    }

    public function destroy($id)
    {
        $categoryDetail = RefCategoryDetail::findOrFail($id);
        $categoryDetail->delete();
        return redirect()->route('admin.ref_category.detail')->with('success', 'Category Detail deleted successfully.');
    }
}
