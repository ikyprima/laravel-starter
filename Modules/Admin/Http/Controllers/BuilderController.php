<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use DB;
use Str;
use Modules\Admin\Facades\Admin;
use Modules\Admin\Database\Schema\SchemaManager;
use Illuminate\Support\MessageBag;
use Modules\Admin\Entities\DataType;
use Modules\Admin\Entities\DataRow;
use Event;
use Route;
use App\Models\Permission;
use Modules\Admin\Events\ClearRoute;
class BuilderController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        // dd(app('App\Models\User'));
        $dataTypes = Admin::model('DataType')->select('id', 'name', 'slug')->get()->keyBy('name')->toArray();

        $tables = array_map(function ($table) use ($dataTypes) {
            $table = Str::replaceFirst(DB::getTablePrefix(), '', $table);

            $table = [
                'prefix'     => DB::getTablePrefix(),
                'name'       => $table,
                'slug'       => $dataTypes[$table]['slug'] ?? null,
                'dataTypeId' => $dataTypes[$table]['id'] ?? null,
            ];

            return (object) $table;
        }, array_diff(SchemaManager::listTableNames(), config('admin.tabelList')) );
       
        return Inertia::render('Admin/Builder/Index',[
            'dataTypes'=> $dataTypes,
            'tables' => collect($tables)->values()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create($table)
    {
        
        $dataType = Admin::model('DataType')->whereName($table)->first();
        
        $data = $this->prepopulateBreadInfo($table);

        $data['fieldOptions'] = SchemaManager::describeTable(
            (isset($dataType) && strlen($dataType->model_name) != 0)
            ? DB::getTablePrefix().app($dataType->model_name)->getTable()
            : DB::getTablePrefix().$table
        );
        
        return Inertia::render('Admin/Builder/Add',[
            'data'=> $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {

        try {
            $dataType = DataType::firstOrCreate([
                'name'=> $request->table
            ], [
                'slug'=> $request->slug,
                'display_name_singular'=> $request->display_name,
                'display_name_plural'=> $request->display_name_plural,
                'model_name'=> $request->model_name,
                'controller'=> $request->table,
            ]);

            $idDataType = $dataType->id;

            foreach ($request->fieldOptions as $key => $item) {

                $dataRows = DataRow::updateOrCreate([
                    'field' =>  $item['field'],
                    'data_type_id' => $idDataType,
                ], [
                    'type' => $item['inputType'],
                    'display_name' => $item['display_name'],
                    'required' => $item['required'],
                    'browse'=> $item['browse'],
                    'read' => $item['read'],
                    'edit'=> $item['edit'],
                    'add' => $item['add'],
                    'delete' => $item['delete'],
                    'order'=> $item['order']
                ]);
            }
            $dataPermission = [
                $request->slug.'-index', $request->slug.'-create',
                $request->slug.'-read', $request->slug.'-update',
                $request->slug.'-delete'
            ];

            foreach ($dataPermission as $key => $value) {
                Permission::firstOrCreate([
                    'name' => $value
                ]);
            }

            Event::dispatch(new ClearRoute());
        //    if (Route::has($request->slug.'.index')) {
        //     return 'tidak ada route';
        //    }else{
            // return ' ada route';
            // Event::dispatch(new ClearRoute());
            // return redirect()->route($request->slug.'.index');
        //     return back(303)->with(['message'=>'Sukses Generate Form']);
        //    }
        
        //    return back(303)->with(['message'=>'Sukses Generate Form']);
            return to_route('builder.index')->with(['message'=>'Sukses Generate Form']);
        } catch (\Illuminate\Database\QueryException $e) {
            $errors = new MessageBag(['error' => [$e->errorInfo[2]]]);
            return back()->withErrors($errors);
        }
        
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('admin::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('admin::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
    private function prepopulateBreadInfo($table)
    {
        $displayName = Str::singular(implode(' ', explode('_', Str::title($table))));
        $modelNamespace = config('voyager.models.namespace', app()->getNamespace());
        if (empty($modelNamespace)) {
            $modelNamespace = app()->getNamespace();
        }

        return [
            'isModelTranslatable'  => true,
            'table'                => $table,
            'slug'                 => Str::slug($table),
            'display_name'         => $displayName,
            'display_name_plural'  => Str::plural($displayName),
            'model_name'           => $modelNamespace.Str::studly(Str::singular($table)),
            'generate_permissions' => true,
            'server_side'          => false,
            'namesp'=> app()->getNamespace()
        ];
    }
}
