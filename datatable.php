<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

if (!function_exists('process_datatable')) {


    /**
     * @param string $model_fullname full model class name. like: Spatie\Permission\Models\Role
     * @param array $fields List of allowed fields to be queried.
     * @param Builder $query query builder instance.
     *
     */
    function processDataTable(Request $request, array $fields, $model_fullname = null, Builder $query = null): array
    {
        if ($model_fullname) {
            $data = new $model_fullname;
        } elseif ($query) {
            $data = $query;
        } else {
            throw new Exception('Bad Method Invocation');
        }

        if (!requestIsValid($request, $fields)) {
            return [
                'status' => 403,
                'message' => 'access to the requested resource is forbidden',
            ];
        }

        $columns = $request->input('columns');
        $recordsTotal = $data->count();
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')]['name']['name'];
        $dir = $request->input('order.0.dir');

        foreach ($columns as $key => $val) {

            if ($request->input('columns.' . $key . '.searchable') == 'true' && $request->input('columns.' . $key . '.search.value') != null) {

                $field = $val['name']['name'];
                $value = $request->input('columns.' . $key . '.search.value');
                $type = $val['name']['type'];

                if ($type == 'text') {
                    $data = $data->where($field, 'LIKE', "%${value}%");
                } elseif ($type == 'between') {
                    $date_values = explode('&', $value);
                    $date_values[0] .= " 00:00:00";
                    $date_values[1] .= " 23:59:59";
                    $data = $data->whereBetween($field, $date_values);
                } elseif ($type == 'select') {
                    $data = $data->where($field, $value);
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

if (!function_exists('requestIsValid')) {
    function requestIsValid(Request $request, array $fields)
    {
        $columns = $request->columns;

        // check column names
        foreach ($columns as $value) {
            $colName = $value['name']['name'];
            $colType = $value['name']['type'];

            // do nothing if column is optional
            if (!in_array($colName, $fields) && $colType != 'option') {
                return false;
            }
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
                $select .= $fields[$i] . ' as ' . $aliases[$i];
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
