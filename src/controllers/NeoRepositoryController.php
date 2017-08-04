<?php

/**
 * Created by Rikard Olsson @ 2017
 */

namespace Znittzel\Zepository\Controllers;

use Znittzel\Zepository\Repositories\NeoRepository;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

interface iNeoRepositoryController {
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

abstract class NeoRepositoryController extends Controller implements iNeoRepositoryController {

	protected $_repository;

    /**
    * Constructor takes a class as parameter. Send the class model as such - Model::class.
    */
	public function __construct($class_model) {
		$this->_repository = new NeoRepository($this, $class_model);
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

        if ($relations = $request->relations) {

            $relation_errors = [];
            foreach ($relations as $relation => $relation_id) {
                
                if (!in_array($relation, $object->relations)) {
                    array_push($relation_errors, $relation. " is not a relation.");
                    continue;
                }

                $relation_instance = $this->_repository->getRelationInstance($relation);

                if (!$relation_object = $relation_instance::find($relation_id)) {
                    array_push($relation_errors, $relation. " does not have object with id ".$relation_id);
                    continue;
                }

                $object->$relation()->save($relation_object);

                // Includes relation in response
                $object->$relation;
            }

            if (!empty($relation_errors))
                array_push($errors, ["relation_errors" => $relation_errors]);
        }

        return $this->buildResponse($object, $errors);
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

        if ($relations = $request->relations) {

            $relation_errors = [];
            foreach ($relations as $relation => $relation_id) {
                
                if (!in_array($relation, $object->relations)) {
                    array_push($relation_errors, $relation. " is not a relation.");
                    continue;
                }

                $relation_instance = $this->_repository->getRelationInstance($relation);

                if (!$relation_object = $relation_instance::find($relation_id)) {
                    array_push($relation_errors, $relation. " does not have object with id ".$relation_id);
                    continue;
                }

                $object->$relation()->attach($relation_object);

                // Includes relation in response
                $object->$relation;
            }

            if (!empty($relation_errors))
                array_push($errors, ["relation_errors" => $relation_errors]);
        }

        return $this->buildResponse($object, $errors);
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