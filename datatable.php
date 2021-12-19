<?php
// error_reporting(E_ALL);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

if (! function_exists('process_datatable')) {


    /**
     * @param Str $model_fullname full model class name. like: Spatie\Permission\Models\Role
     * @param array $fields List of allowed fields to be queried. 
     * @param Str $query query builder instance. 
     * 
     */
    function processDataTable(Request $request, $model_fullname, array $fields=[], $query = '')
    {
        if (!empty($model_fullname)) {
            $data = new $model_fullname;
        } elseif (!empty($query)) {
            $data = $query;
        } else {
            return [
                'status'  => 500,
                'message' => 'bad method invokation.'
            ];
        }

        if (!requestIsValid($request, $fields)) {
            return [
            'status' => 403,
            'message' => 'access to the requested resource is forbidden',
        ];
        }

        $columns = getColumns($request);
        
        $recordsTotal = $data->count();
        $recordsFiltered = $recordsTotal;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')]['name']['name'];

        
        $dir = $request->input('order.0.dir');

        // create searchable fields array
        $search_fields = [];
        $is_searchable = false;

        foreach ($columns as $key => $value) {
            $name_array = $columns[$key]['name'];
            $rel = null;

            if ($request->input('columns.'.$key.'.searchable') == 'true') {
                $arr = array(
                    'value'    => $request->input('columns.'.$key.'.search.value'),
                    'type'     => $columns[$key]['name']['type'],
                );

                $field = $columns[$key]['name']['name'];

                $search_fields[$field] = $arr;
                $is_searchable = true;
            }
        }

        foreach ($search_fields as $field => $attributes) {
            if (isset($attributes['value'])) {
                $value = $attributes['value'];
                    
                if ($attributes['type'] == 'text') {
                    $data = $data->where($field, 'LIKE', "%${value}%");
                } elseif ($attributes['type'] == 'date') {
                    $date_values = explode('&', $value);
                    $date_values[0] .= " 00:00:00";
                    $date_values[1] .= " 23:59:59";
                    $data = $data->whereBetween($field, $date_values);
                }
            }
        }

        $recordsFiltered = $data->count();
        $data = $data->offset($start)
                           ->limit($limit)
                           ->orderBy($order, $dir)
                           ->get();

        return [
            'status' => 200,
            'draw' => $request->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ];
    }
}

if (! function_exists('getColumns')) {
    function getColumns(Request $request)
    {
        $columns = [];
        foreach ($request->columns as $col) {
            $columns[] = $col;
        }

        return $columns;
    }
}

if (!function_exists('requestIsValid')) {
    function requestIsValid(Request $request, array $fields)
    {
        $columns = getColumns($request);
        
        // check column names
        foreach ($columns as $key => $value) {
            $colName = $columns[$key]['name']['name'];
            $colType = $columns[$key]['name']['type'];
            
            // do nothing if column is optional
            if (!in_array($colName, $fields) && $colType != 'option') {
                return false;
            }
        }

        // check order name
        $orderName = $columns[$request->input('order.0.column')]['name']['name'];
        if (!in_array($orderName, $fields)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('makeSelectQuery')) {
    function makeSelectQuery(array $fields, array $aliases)
    {
        $select = '';
        for ($i = 0; $i < count($fields); $i++) {
            if ($aliases[$i]) {
                $select .= $fields[$i]. ' as '. $aliases[$i];
            } else {
                $select .= $fields[$i];
            }

            if ($i != count($fields) - 1) {
                $select .= ', ';
            }
        }

        return $select;
    }
}
