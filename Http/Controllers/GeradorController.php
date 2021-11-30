<?php

namespace Modules\Gerador\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Modules\Gerador\Entities;

class GeradorController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('gerador::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('gerador::create');
    }

    /**
    * Store a newly created resource in storage.
    *
    * @param Request $request
    *
    * @return RedirectResponse|Redirector
    */
    public function store(Request $request)
    {
       $requestData = $request->all();
       gerador::create($requestData);
       return redirect('admin/gerador')->with('flash_message', 'gerador adicionada!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return View
     */
    public function show($id)
    {
        $cidade = gerador::findOrFail($id);
        return view('gerador::show',compact('gerador'));
    }

    /**
    * Show the form for editing the specified resource.
    *
    * @param  int  $id
    *
    * @return View
    */
    public function edit($id)
    {
        $gerador = gerador::findOrFail($id);
        return view('gerador::edit',compact('gerador'));
    }

   /**
    * Update the specified resource in storage.
    *
    * @param Request $request
    * @param  int  $id
    *
    * @return RedirectResponse|Redirector
    */
    public function update(Request $request, $id)
    {
        $requestData = $request->all();
        $gerador = gerador::findOrFail($id);
        $gerador->update($requestData);
        return redirect('admin/gerador')->with('flash_message', 'Cadastro gerador Alterado!');
    }

   /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    *
    * @return RedirectResponse|Redirector
    */
    public function destroy($id)
    {
        cidade::destroy($id);
        return redirect('admin/gerador')->with('flash_message', 'registro Excluído!');
    }
}
