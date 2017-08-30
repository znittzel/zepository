<?php

/**
 * Created by Rikard Olsson @ 2017
 */

namespace Znittzel\Zepository\Controllers;

use Znittzel\Zepository\Repositories\Repository;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

interface iRepositoryController {
    /**
     * Validate before storing model.
     * Returns boolean.
     */
    public function validateStore(Request $request);

    /**
     * Validate before updating model. 
     * Returns boolean.
     */
    public function validateUpdate(Request $request);
}

abstract class RepositoryController extends Controller implements iRepositoryController {

    protected $_repository;

    /**
    * Constructor takes a class as parameter. Send the class model as such - Model::class.
    */
    public function __construct($class_model) {
        $this->_repository = new Repository($this, $class_model);
    }

    /**
    * Builds a default response.
    */
    public function buildResponse($result, $errors = [], $code = 200) {
        $res = new \StdClass;

        $res->result = $result;
        $res->errors = $errors;

        return response()->json($res, $code);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $collection = $this->_repository->get($errors, $request);

        return $this->buildResponse($collection, $errors);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $object = $this->_repository->store($errors, $request);

        if (!empty($errors))
            return $this->buildResponse(null, $errors);

        $object->save();

        return $this->buildResponse($object);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $object = $this->_repository->first($errors, $request, $id);

        return $this->buildResponse($object, $errors);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $object = $this->_repository->update($errors, $request, $id);

        if (!empty($errors))
            return $this->buildResponse(null, $errors);

        $object->update();

        return $this->buildResponse($object);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $object = $this->_repository->destroy($errors, $request, $id);

        if (!empty($errors))
            return $this->buildResponse(null, $errors);

        $object->delete();

        return $this->buildResponse(["deleted" => $object->id]);
    }
}