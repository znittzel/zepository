<?php

/**
 * Created by Rikard Olsson @ 2017
 */

namespace Znittzel\Zepository\Repositories;

use Illuminate\Http\Request;

/**
 * Repository implements iRepository to later be inherited. Protected vars will be set to whatever suits the repository.
 * Class cannot be instantiated due to that each class inherit from Repository will have different functions.
 */
class NeoRepository {

    /**
     * Determine what model to use
     */
    private $_classModel;

    /**
     * Connected controller
     */
    protected $_controller;

	/**
	 * $_filters - What's allowed to use as a filter.
	 */
	protected $_filters = []; 

	/**
	 * $_orders - What order direction can be used.
	 */
    private $_orders = [
        "asc",
        "desc"
    ];

    /**
     * $_paginateLimits - Limits of what is ok to paginate 
     */
    protected $_paginateLimits = ["lower" => 5, "higher" => 1000];

    /**
     * Takes the magic ::class constans. E.g. Event::class.
     */
    public function __construct($controller, $model) {
        $this->_classModel = $model;
        $this->_controller = $controller;
    }

    /**
     * Returns a query instance of givin class model
     */
    private function getModelInstance() {
        return new $this->_classModel;
    }

    /**
     * Returns a query instance of givin relation model
     */
    public function getRelationInstance($relation) {
        $class = "App\\".$relation;

        return new $class();
    }

    /**
     * Returns a requested collection of class model instances.
     */
    public function get(&$errors, $request) {
        
        // Initialize query of model class
        $query = $this->getModelInstance()->select();

        // Initialize errors array
        $errors = [];

        // Join relations in query
        if ($with = $request->with)
            $this->with($query, $errors, $with);

        // Add where constraint if requested
        if ($where = $request->where)
            $this->where($query, $errors, $where);

        // Filter query
        if ($filter = $request->filter)
            $this->filter($query, $errors, $filter);

        // Order query
        if ($orderBy = $request->orderBy)
            $this->orderBy($query, $errors, $orderBy);

        // Limit Query
        if ($limit = $request->limit) {
            $this->limit($query, $errors, $limit);
        }

        // Get collection result of query as pagination or all
        $collection = $this->paginate($query, $request);

        // Order collection by relation property
        if ($orderByRelation = $request->orderByRelation) {
            $this->orderByRelation($collection, $errors, $orderByRelation);
        }

        // Return collection
        return $collection;
    }

    /**
     * Returns the first instance of a collection result as $this->_classModel.
     */
    public function first(&$errors, $request, $id) {
            
        // Initialize query of model class
        $query = $this->getModelInstance();

        // Initialize query
        $query = $query::whereId($id);

        // Initialize errors array
        $errors = [];

        // Join relations if requested
        if ($with = $request->with)
            $this->with($query, $errors, $with);

        // Add where constraint if requested
        if ($where = $request->where)
            $this->where($query, $errors, $where);

        // Order by could be requested to order relation
        if ($orderBy = $request->orderBy)
            $this->orderBy($query, $errors, $orderBy);

        // Return the first model of result
        return $query->first();
    }

    /**
     * Performs a standard store procedure. Returns a model instance to later be saved or added more values.
     */
    public function store(&$errors, $request) {

        // Init new errors array if not passed
        if ($errors == null)
            $errors = [];

        // Get validator with validated data
        $validator = $this->_controller->validateStore($request);

        // Sent back errors if validation fails
        if ($validator->fails()) {
            array_push($errors, ["could_not_store_model" => $validator->errors()]);
            return;
        }

        // Init model instance
        $model = $this->getModelInstance();

        // Fill the attributes
        $model->fill($request->all());

        // Return model instance
        return $model;
    }

    public function update(&$errors, $request, $id) {

        // Init new errors array if not passed
        if ($errors == null)
            $errors = [];

        // Create query instance
        $query = $this->getModelInstance(); 

        // Find model
        if (!$model = $query->find($id)) {
            array_push($errors, ["could_not_find_model" => $id]);
            return;
        }

        // Get validator with validated data
        $validator = $this->_controller->validateUpdate($request);

        // Sent back errors if validation fails
        if ($validator->fails()) {
            array_push($errors, ["could_not_update_model" => $validator->errors()]);
            return;
        }

        // Fill model with requested data
        $model->fill($request->all());

        // Return model instance
        return $model;
    }

    public function destroy(&$errors, $request, $id) {
        // Init new errors array if not passed
        if ($errors == null)
            $errors = [];

        // Create query instance
        $query = $this->getModelInstance();

        // Find requested model for deletion
        if (!$model = $query->find($id)) {
            array_push($errors, ["could_not_find_model" => $id]);
            return;
        }

        // Model prepared for deletion. Return model.
        return $model;
    }

    /**
     * Includes relational data to the query. What's allowed to be included is set in the $_relations variable.
     */
    public function with(&$query, &$errors, $withs) {

    	// Initialize a particular error array to include specific with-errors.
        $with_errors = [];

        // Get class model instance
        $instance = $this->getModelInstance();

        // Check if relations var is defined
        if (!property_exists($instance, 'relations')) {

            // Add error
            array_push($errors, ["with" => "query_by_with_not_activated"]);

            // Do not execute
            return;

        }

        // Explode each with-statement into an array
        $relations = explode(',', $withs);

        // Iterate through each with-statement as a relation
        foreach ($relations as $relation) {
            // Init has_count to tell if this relation collection should be counted
            $has_count = false;

            // If it should be counted, remove the exclamation mark
            if ($has_count = strpos($relation, '!')) {
                $relation = str_replace('!', '', $relation);
            }

        	// If relation is allowed, included it in query else set is as an error
            if (in_array($relation, $instance->relations)) {
                // Include the relation in query
                $query->with($relation);

                // If it should be counted, include count in query
                if ($has_count)
                    $query->withCount(explode('.', $relation)[0]);

            } else {
                array_push($with_errors, $relation);
            }
        }

        // If with_errors is not empty, push it into errors-array
        if (!empty($with_errors))
            array_push($errors, ["with" => [ "relation_do_not_exists_or_is_unavailable" => $with_errors ]]);
    }

    /**
     * Will order the query's result.
     */
    public function orderBy(&$query, &$errors, $orderBy) {

        // Get instance
        $instance = $this->getModelInstance();

        // Check if order by exists in class
        if (!property_exists($instance, 'orderBys')) {

            // Push error
            array_push($errors, ['orderBy' => "is_not_allowed"]);

            // Do not execute more
            return;

        }

    	// Explodes string into array where 0 is what to order by and 1 is if asc or desc.
        $order_result = explode(':', $orderBy);

        // Initialize a default order direction
        $order = $this->_orders[0];

        // Check to see if an order direction was provided
        if (count($order_result) == 2) {

        	// Set the order direction that was provided
            $order = $order_result[1];

            // Set what to order by from string
            $orderBy = $order_result[0];
        }

        // Get fillables from model
        $fillables = $instance->getFillable();

        // Check if the order by is allowed
        if (in_array($orderBy, $fillables) || in_array($orderBy, $instance->orderBys)) {

            // Check if orderBy is a order on a relation
            $relation_property = explode('.', $orderBy);

            // If the order direction was allowed set it else use the default order direction
            $order = in_array($order, $this->_orders) ? $order : $this->_orders[0];

            // Include order by to query
            $query->orderBy($orderBy, $order);

        } else { 

        	// If the order by was not allowed, push it to errors array
            array_push($errors, ['orderBy' => $orderBy]);
        }
    }

    /**
     * This filters by adding constraints to a query. The input of filters_string HAS to be written in this format (example): "price[1000:12000],datetime[2017-02-03:2017-04-04]"
     */
    public function filter(&$query, &$errors, $filters_string) {
    	
        // Filter-string is array, return with error.
        if (is_array($filters_string)) {
            array_push($errors, ["filter" => "incorrect_filter_specified"]);
            return;
        }

        // Initialize filter errors array to contain specific filter errors
        $filter_errors = [];

        // Explode $filters_string by , into array
        $filters = explode(',', $filters_string);

        // Get keys from $this->_filters
        $filter_keys = array_keys($this->_filters);

        foreach ($filters as $filter) {
            // Get key word from filter string
            preg_match("/(.+)\[.+\]/", $filter, $filter_match);

            // If no match found, push error into filter_errors and break iteration.
            if (empty($filter_match)) {
                array_push($filter_errors, ["incorrect_filter_specified" => $filter]);
                break;
            }

            // Get key from match (e.g. price)
            $filter_key = $filter_match[1];

            // Check if key is allowed
            if (!in_array($filter_key, $filter_keys)) {
                array_push($filter_errors, ["is_not_a_filter" => $filter_key]);
                break;
            }

            // Get lower and higher values from string
            preg_match("/\[(.+):(.+)\]/", $filter, $match);

            // If no match, push error and break iteration
            if (empty($match) || count($match) < 3 ) {
            	array_push($filter_errors, ["could_not_use_filter" => $filter]);
                break;
            }

            // Transfer into lower and higher vars
            $lower = $match[1];
            $higher = $match[2];
            
            // Create and validate data with Validator
            $validation = \Validator::make([
            		$filter_key."_min" => $lower, 
            		$filter_key."_max" => $higher
            	], [
            		$filter_key."_min" => $this->_filters[$filter_key]["validation"],
            		$filter_key."_max" => $this->_filters[$filter_key]["validation"]
            	]);

            // Push error and break iteration if validation failed
            if ($validation->fails()) {
            	array_push($filter_errors, ["filter_not_valid_values" => $validation->errors()]);
            	break;
            }

            // Everything is clear. Use provided filter function with lower and higher value
            $this->_filters[$filter_key]["function"]($query, $lower, $higher);
        }

        // If filter_errors contain errors, push them to the errors array
        if (!empty($filter_errors))
            array_push($errors, ["filter" => $filter_errors]);
    }

    /**
     * Paginates result to number or all if number is not set.
     */
    public function paginate($query, $request) {
        // Get lowest pagination number
		$paginate = $this->_paginateLimits["lower"];

        // Get paginate number
        $number = $request->paginate;

        // Check if number is valid
		if (isset($number) && is_numeric($number)) { 

            // Convert number to integer
			$number += 0;

            // Check if number is in range
			if ($number >= $this->_paginateLimits["lower"] 
				&& $number <= $this->_paginateLimits["higher"] 

                // ...and is not a float
				&& !is_float($number)) 
			{
				$paginate = $number;
			}

            // Paginate query
            $paginateResult = $query->paginate($paginate);

            // Append query params
            $paginateResult->appends($_GET)->links();

            // Return result
            return $paginateResult;
		}

        // Return all
		return $query->get();
	}

    /**
     * Querying based on where as in a where-clause
     * Allowed operators are =, >, <, >=, <=
     */
    public function where(&$query, &$errors, $where) {
        // Init where-errors
        $where_errors = [];

        // Get key word from filter string
        preg_match("/\[(.+)\]/", $where, $where_match);

        // If no match found, push error into filter_errors and break iteration.
        if (empty($where_match)) {
            array_push($where_errors, ["incorrect_where_specified" => $where]);
        } else {
            // Keep executing if found match
            // Explode each where into array
            $where_records = explode(',', $where_match[1]);

            // Get fillables from model
            $fillables = $this->getModelInstance()->getFillable();

            // Iterate through where_records
            foreach ($where_records as $record) {
                // Init vars
                $key = null;
                $operator = null;
                $value = null; 

                // Translate into key and value as: key (e.g. "first_name") and value (e.g. "Rikard")

                // Check which operator is used
                $equal_record = explode('=', $record);
                $greater_record = explode('>', $record);
                $less_record = explode('<', $record);

                if (count($equal_record) > 1) {
                    $equalGreater_record = explode('>=', $record);
                    $equalLess_record = explode('<=', $record);

                    // Check if =, >= or <=
                    if (count($equalGreater_record) > 1) {
                        $operator = ">=";
                        $key = $equalGreater_record[0];
                        $value = $equalGreater_record[1];
                    } else if (count($equalLess_record) > 1) {
                        $operator = "<=";
                        $key = $equalLess_record[0];
                        $value = $equalLess_record[1];
                    } else {
                        $operator = "=";
                        $key = $equal_record[0];
                        $value = $equal_record[1];
                    }

                } else if (count($greater_record) > 1) {
                    $operator = ">";
                    $key = $greater_record[0];
                    $value = $greater_record[1];
                } else if (count($less_record) > 1) {
                    $operator = "<";
                    $key = $less_record[0];
                    $value = $less_record[1];
                }

                // Check that all vars has value
                if ($key != null && $operator != null && $value != null) {

                    // Check if model contains key
                    if (in_array($key, $fillables)) {

                        // Perform where query
                        $query->where($key, $operator, $value);

                    } else {

                        // Key maybe a relation
                        $subKeyValue = explode('.', $key);

                        // Check if succeded explode
                        if (count($subKeyValue) == 2) {

                            // Get relation name
                            $relation = $subKeyValue[0];

                            // Get subkey
                            $subKey = $subKeyValue[1];

                            // Check that key is a relation
                            if (in_array($relation, $this->_relations)) {

                                // Query on relation
                                $query->whereHas($relation, function($query) use ($subKey, $operator, $value) {
                                    $query->where($subKey, $operator, $value);
                                });

                            } else {
                                array_push($where_errors, ["sub_key_not_a_valid_relation" => $relation]);
                            }

                        } else {
                            array_push($where_errors, ["key_not_a_valid_where_property" => $key]);
                        }
                    }

                } else {
                    // Push error if one is missing
                    array_push($where_errors, ["check_value_key_operator" => $record]);
                }
            }
        }

        // If where-errors contain errors, push them to the errors array
        if (!empty($where_errors))
            array_push($errors, ['where' => $where_errors]);
    }


    /**
     * Querying a limited result
     * $limit is an integer
     */
    public function limit(&$query, &$errors, $limit) {

        // Convert to integer
        $limit = intval($limit);
        
        // Check limit var to be an int
        if (!is_int($limit)) {
            array_push($errors, ["limit_is_not_an_integer" => $limit]);
            return;
        }

        // Check limit to be larger than 0
        if ($limit <= 0) {
            array_push($errors, ["limit_has_to_be_larger_than_0" => $limit]);
            return;
        }

        // Limit the query
        $query->limit($limit);
    }

    /**
     * Order by models relation. Order by relation is only possible after getting collection from query.
     */
    public function orderByRelation(&$collection, &$errors, $orderByRelation) {

        // Check if orderByRelation is active
        if (!property_exists($this, '_orderByRelations')) {

            // Push error
            array_push($errors, "orderByRelation_not_activated_on_this_resource");

            // Do not execute more
            return;
        }
        
        // Explodes string into array where 0 is what to order by and 1 is if asc or desc.
        $order_result = explode(':', $orderByRelation);

        // Initialize a default order direction
        $order = $this->_orders[0];

        // Check to see if an order direction was provided
        if (count($order_result) == 2) {
            // Set the order direction that was provided
            $order = $order_result[1];

            // Set what to order by from string
            $orderBy = $order_result[0];
        } else {

            // Else the order_result and orderBy is the same
            $orderBy = $order_result[0];
        }

        // Get relation and property from orderBy
        $relation_property = explode('.', $orderBy);

        if (count($relation_property) == 2) {

            // Append relation
            $relation = $relation_property[0];

            // Append property
            $property = $relation_property[1];

            // Check if relation is allowed to order by
            if (in_array($relation, $this->_orderByRelations)) {

                // Get relations instance
                $relation_class = $this->getRelationInstance(ucwords(rtrim($relation, 's'))); 

                // Check if property exists in relation
                if (in_array($property, $relation_class->getFillable())) {

                    // Check if collection is paginated or not
                    if ($collection instanceof \Illuminate\Pagination\LengthAwarePaginator) {

                        // Get array from paginated collection and sort
                        $collection->items()->sort(function($item, $key) use ($relation, $property) {
                            return $item->$relation->$property;
                        });

                    } else {

                        // Sort right on collection
                        $collection->sort(function($item, $key) use ($relation, $property) {
                            return $item->$relation->$property;
                        });

                    }

                } else {

                    // Push error
                    array_push($errors, ["property_not_found_in_relation" => $property]);
                }

            } else {

                // Push error
                array_push($errors, ["relation_not_allowed_ordering_by" => $relation]);

            }

        } else {

            // Push error
            array_push($errors, ["orderByRelation_needs_a_relation_and_a_property" => $orderByRelation]);

        }
    }
}