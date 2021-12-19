<?php
class UserController extends Controller
{
    public function show(Request $request)
    {
        
        $fields = ['users.id', 'users.firstname', 'users.lastname',
                   'users.email', 'users.username', 'users.position', 'roles.id' , 'roles.name_fa', 'users.updated_at'];
        $aliases = ['', '', '', '', '', '', 'role_id', 'role_name_fa', ''];

        $select = makeSelectQuery($fields, $aliases);
        $query = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->selectRaw($select);

        $results = processDataTable($request, $model_fullname=[], $fields, $query);
        return response()->json($results, $results['status']);
    }
}
