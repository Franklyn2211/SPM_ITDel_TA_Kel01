<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefCategory;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    public function index()
    {
        $category = RefCategory::all();
        $roles = Role::all();
        return view('admin.roles.add', ['roles' => $roles, 'category' => $category]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
        ], [
            'name.unique' => 'Role tersebut sudah terdaftar.',
        ]);

        $role = new Role([
            'id' => Role::generateNextId(),
            'name' => $request->get('name'),
        ]);

        $category = RefCategory::findOrFail($request->get('category_id'));
        $role->category()->associate($category);

        $role->save();
        return redirect()->route('admin.roles.add')->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles','name')->ignore($role->id, 'id'),
        ],
        ], [
            'name.unique' => 'Role tersebut sudah terdaftar.',
        ]);

        $data = [
            'name' => $request->get('name')
        ];

        if ($request->has('category_id')) {
            $category = RefCategory::findOrFail($request->get('category_id'));
            $role->category()->associate($category);
        }

        $role->update($data);

        return redirect()->route('admin.roles.add')->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return redirect()->route('admin.roles.add')->with('success', 'Role deleted successfully.');
    }
}
