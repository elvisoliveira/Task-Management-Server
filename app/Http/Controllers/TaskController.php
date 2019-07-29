<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Task;
use App\User;
use Illuminate\Validation\ValidationException;
use Auth;
use Illuminate\Validation\Rule;
use DateTime;
use Carbon\Carbon;

use App\Mail\TaskAssignedMail;
use App\Jobs\SendMail;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function isNotAssignable($current_user, $assigned_to)
    {
        $assignee_user = User::find($assigned_to);

        if ($current_user->role === 'normal' && $assigned_to !== $current_user->id) {
            return response()->json(['message'=> 'A normal user can only assign tasks to himself/herself.'], 403);
        }

        if (!$assignee_user || $assignee_user->deleted_at) {
            return response()->json(['message'=> 'The user with this id does not exist.'], 403);
        }

        return false;
    }

    /** This will create a new task */
    public function create(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'assigned_to' => 'bail|required|integer',
            'due_date' => 'bail|required|date_format:Y-m-d H:i:s',
            'title' => 'bail|required|max:255',
            'assigned_at' => 'bail|date_format:Y-m-d H:i:s',
        ];
        $this->validate($request, $rules);

        $assigned_to =  (int)$request->input('assigned_to');

        $varble = $this->isNotAssignable($current_user, $assigned_to);
        if ($varble) {
            return $varble;
        }
        $assignee_user = User::find($assigned_to);
        $due_date =  $request->input('due_date');
        $assigned_at = $request->input('assigned_at');
        $title =  $request->input('title');
        $description =  $request->input('description');

        $today_hour = new Carbon();
        $today = $today_hour->format('Y-m-d H:i:s');
        if (!$assigned_at) {
            $assigned_at = $today;
        }
        if ($due_date <= $assigned_at) {
            return response()->json(['message' => 'Sorry, please give the user some time to complete the task.'], 422);
        }
        $new_task = new Task;
        $new_task->assigned_to =$assigned_to;
        $new_task->assigned_by = $current_user->id;
        $new_task->due_date = $due_date;
        $new_task->title = $title;
        $new_task->description = $description;
        $new_task->assigned_at = $assigned_at;
        $new_task->save();
        if ($new_task->assigned_by!==$new_task->assigned_to) {
            $due_date = Carbon::parse($due_date)->toDayDateTimeString();
            $mail = new TaskAssignedMail($new_task, $due_date, $current_user->name);
            dispatch(new SendMail($assignee_user->email, $mail));
        }
        return response()->json([
            'message'=>'Task Successfully Created'
        ], 201);
    }


    public function getAssignableUsers(Request $request)
    {
        $current_user = Auth::user();

        $rules = [
            'filter_name' => 'bail|max:255'
        ];
        $this->validate($request, $rules);

        if ($current_user->role !== 'admin') {
            return response()->json(['message'=> 'A normal user can only assign tasks to himself/herself'], 403);
        }

        $filter_name = $request->input('filter_name');
        $query = User::where('name', 'like', '%'.$filter_name.'%');
        $query
        ->where('users.deleted_at', null)
        ->select('id as user_id', 'name', 'email');
        $table = $query->paginate(20);
        return response()->json(['table'=> $table], 200);
    }

    /** This will update the task details. */
    public function update(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'task_id' => 'bail|required|integer|exists:tasks,id',
            'due_date' => 'bail|required|date_format:Y-m-d H:i:s',
            'title' => 'bail|required|max:255',
            'updated_at' => 'bail|date_format:Y-m-d H:i:s',
        ];
        $this->validate($request, $rules);

        $task_id = $request->input('task_id');
        $task = Task::find($task_id);

        /** Check whether task exists or not, or task assigner is same as current user */
        if ($task->deleted_at || $task->assigned_by !== $current_user->id) {
            return response()->json(['message'=>'Task does not exist'], 404);
        }


        $due_date =  $request->input('due_date');
        $updated_at = $request->input('updated_at');

        $today_hour = new Carbon();
        $today = $today_hour->format('Y-m-d H:i:s');
        if (!$updated_at) {
            $updated_at = $today;
        }

        if ($due_date <= $updated_at) {
            return response()->json(['message' => 'Sorry, please give the user some time to complete the task.'], 422);
        }
        $title =  $request->input('title');
        $description =  $request->input('description');
        $task->due_date = $due_date;
        $task->title = $title;
        $task->description = $description;
        $task->save();

        return response()->json([
            'message'=>'Task Successfully Updated'
        ], 201);
    }

    /** This will delete a task. */
    public function delete(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'task_id' => 'bail|required|integer|exists:tasks,id',
        ];
        $this->validate($request, $rules);
        $task_id = $request->input('task_id');
        $task = Task::find($task_id);

        if ($task->deleted_at || $task->assigned_by !== $current_user->id) {
            return response()->json(['message'=>'Task does not exist'], 404);
        }
        $task->status = 'deleted';
        $today = new Carbon();
        $today = $today->format('Y-m-d H:i:s');
        $task->deleted_at = $today;
        $task->save();

        return response()->json([
            'message'=>'Task Successfully Deleted'
        ], 201);
    }

    /** This will update the status of the task. Can be peformed by only assignee */
    public function update_status(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'task_id' => 'bail|required|integer|exists:tasks,id',
            'status' => ['bail','required', Rule::in(['in-progress','completed'])]
        ];
        $custom_messages = [
            'in' => "The status can be - 'in-progress' or 'completed'",
        ];
        $this->validate($request, $rules, $custom_messages);

        $task_id = $request->input('task_id');
        $task = Task::find($task_id);

        if ($task->deleted_at) {
            return response()->json(['message'=>'Task does not exist'], 404);
        }

        if ($task->assigned_by === $current_user->id && $task->assigned_to !== $current_user->id) {
            return response()->json(['message'=>'Only assignee can update the task status'], 403);
        }

        if ($task->assigned_to !== $current_user->id) {
            return response()->json(['message'=>'Task does not exist'], 404);
        }

        if ($task->status === 'completed') {
            return response()->json(['message'=>'Task already completed'], 403);
        }

        $status = $request->input('status');
        $task->status = $status;

        // Update the completed_at if status is being updated to completed
        if ($status === 'completed') {
            $today = new Carbon();
            $today = $today->format('Y-m-d H:i:s');
            $task->completed_at = $today;
        }
        $task->save();
        return response()->json([
            'message'=>'Task Status Successfully Updated'
        ], 201);
    }

    public function filter(Request $request)
    {
        $current_user = Auth::user();

        $normal = [
            'tasks.id as task_id',
            'tasks.title',
            'tasks.description',
            'tasks.assigned_by',
            'tasks.due_date',
            'tasks.status',
            'tasks.assigned_at'
        ];
        $admin = $normal;
        $admin[] = 'tasks.assigned_to';
        
        $rules=[
            'filter_assigned_by' => 'bail|integer',
            'filter_text' => 'bail|max:255',
            'filter_assigner' => 'bail|max:255',
            'filter_assignee' => 'bail|max:255',
            'filter_status' => ['bail', Rule::in(['assigned','in-progress','completed','deleted'])],
            'filter_from_date' => 'bail|date_format:Y-m-d H:i:s',
            'filter_to_date' => 'bail|date_format:Y-m-d H:i:s'
        ];
        $custom_messages = [
            'in' => "The status can be - 'assigned', 'in-progress', 'completed' or 'deleted'",
        ];
        $this->validate($request, $rules, $custom_messages);

        $filter_assigned_by = $request->input('filter_assigned_by');
        $filter_text = $request->input('filter_text');
        $filter_assigner = $request->input('filter_assigner');
        $filter_assignee = $request->input('filter_assignee');
        $filter_from_date = $request->input('filter_from_date');
        $filter_to_date = $request->input('filter_to_date');
        $filter_status = $request->input('filter_status');

        /** Conditions based on assignee */
        $query = Task::whereHas('assigned_to', function ($query) use ($current_user, $filter_assignee) {
            $query->whereNull('deleted_at');
            if ($current_user->role === 'normal') {
                $query = $query->where('id', $current_user->id);
            } elseif ($current_user->role === 'admin') {
                $query = $query->where('name', 'like', '%'.$filter_assignee.'%');
            }
        });

        /** Conditions based on assigner */
        $query->whereHas('assigned_by', function ($query) use ($current_user, $filter_assigner, $filter_assigned_by) {
            $query->whereNull('deleted_at')
            ->where('name', 'like', '%'.$filter_assigner.'%');
            if ($filter_assigned_by) {
                $query->where('id', $filter_assigned_by);
            }
        });

        if ($filter_status !== 'deleted') {
            $query->whereNull('deleted_at');
        }

        if ($filter_text) {
            $query = $query
            ->where(function ($query) use ($filter_text) {
                $query
                ->where('title', 'like', '%'.$filter_text.'%')
                ->orWhere('description', 'like', '%'.$filter_text.'%');
            });
        }

        if ($filter_from_date) {
            $query = $query->where('assigned_at', '>=', $filter_from_date);
        }

        if ($filter_to_date) {
            $query = $query->where('assigned_at', '<=', $filter_to_date);
        }

        $query->where('status', 'like', '%'.$filter_status.'%');
        $query->with('assigned_by:id,name');

        /** Select columns according to the role */
        if ($current_user->role === 'normal') {
            $query = $query->select($normal);
        } elseif ($current_user->role === 'admin') {
            $query->with('assigned_to:id,name');
            $query = $query->select($admin);
        }

        $filter_tasks = $query->paginate(5);

        return response()->json([
            'message'=>'Filtered Successfully',
            'table'=>$filter_tasks,
        ], 200);
    }

    public function dashboardTasks(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'user_id' => 'bail|integer'
        ];
        $this->validate($request, $rules);

        $user_id = $request->input('user_id');
        if (!$user_id) {
            $user_id = $current_user->id;
        }

        $query = Task::where('assigned_to', $user_id)
                ->where('status', '!=', 'deleted')
                ->where('status', '!=', 'completed')
                ->orderBy('due_date', 'asc')
                ->with('assigned_by:id,name')
                ->select('id', 'title', 'description', 'status', 'due_date', 'assigned_at', 'assigned_by')
                ->paginate(2);

        return response()->json([
            'message' => 'Successful Query',
            'table' => $query,
        ], 200);
    }

    public function dashboard(Request $request)
    {
        $current_user = Auth::user();
        $today = new Carbon();
        $today = $today->format('Y-m-d H:i:s');
        $rules = [
            'from_date'=> 'bail|date_format:Y-m-d H:i:s',
            'assigned_by'=> 'bail|integer',
            'assigned_to'=> 'bail|integer',
            'to_date'=> 'bail|date_format:Y-m-d H:i:s',
            'this_year' => 'bail|date_format:Y',
            'last_year' => 'bail|date_format:Y',
            'last_two_year' => 'bail|date_format:Y',
            'last_six_month' => 'bail|digits_between:1,12',
        ];
        $this->validate($request, $rules);

        $from_date = $request->input('from_date');
        $assigned_by = $request->input('assigned_by');
        $assigned_to = $request->input('assigned_to');
        $to_date = $request->input('to_date');

        if ($current_user->role === 'normal') {
            $tasks = Task::where('assigned_to', $current_user->id)
            ->where('status', '!=', 'deleted');
        } elseif ($current_user->role === 'admin' && $assigned_to) {
            $tasks = Task::where('assigned_to', $assigned_to)
            ->where('status', '!=', 'deleted');
        } else {
            $tasks = Task::where('assigned_to', $current_user->id)
            ->where('status', '!=', 'deleted');
        }
        $tasks
        ->where(function ($query) use ($current_user) {
            $query->whereColumn('assigned_by', '!=', 'assigned_to')
            ->orWhere('assigned_by', $current_user->id);
        });

        if ($from_date) {
            $tasks
            ->where('assigned_at', '>=', $from_date);
        }
        if ($to_date) {
            $tasks
            ->where('assigned_at', '<=', $to_date);
        }
        if ($assigned_by) {
            $tasks
            ->where('assigned_by', $assigned_by);
        }
        $total_tasks = clone $tasks;
        $completed_tasks_before = clone $tasks;
        $completed_tasks_after = clone $tasks;
        $overdue_tasks = clone $tasks;
        $in_progress_tasks = clone $tasks;
        $no_activity_tasks = clone $tasks;

        $completed_tasks_before = $completed_tasks_before
        ->where('status', 'completed')
        ->whereColumn('due_date', '>=', 'completed_at');

        $completed_tasks_after = $completed_tasks_after
        ->where('status', 'completed')
        ->whereColumn('due_date', '<', 'completed_at');

        $overdue_tasks = $overdue_tasks
        ->where('status', '!=', 'completed')
        ->where('due_date', '<', $today);

        $in_progress_tasks = $in_progress_tasks
        ->where('status', 'in-progress')
        ->where('due_date', '>=', $today);

        $no_activity_tasks = $no_activity_tasks
        ->where('status', 'assigned')
        ->where('due_date', '>=', $today);
        
        $performance_data = [
            ['name' => 'Completed on time', 'y' =>  $completed_tasks_before->count(),],
            ['name' => 'Completed after deadline', 'y' =>  $completed_tasks_after->count(),],
            ['name' => 'Overdue Tasks', 'y' =>  $overdue_tasks->count()],
            ['name' => 'In progress', 'y' =>  $in_progress_tasks->count(),],
            ['name' => 'No activity', 'y' =>  $no_activity_tasks->count(),],
        ];
        return response()->json([
            'message' => 'Successful Query',
            'performance_data' => $performance_data,
        ], 200);
    }

    public function task(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'task_id' => 'bail|required|integer|exists:tasks,id',
        ];
        $this->validate($request, $rules);

        $task_id = $request->input('task_id');
        $task = Task::find($task_id);

        /** Check whether task exists or not, or task assigner is same as current user */
        if (($task->assigned_to !== $current_user->id && $task->assigned_by !== $current_user->id && $current_user->role!=="admin")) {
            return response()->json([
                'message'=>'Task does not exist',
            ], 404);
        }

        return response()->json([
            'message'=> 'Query Successful',
            'task'=> $task,
        ], 200);
    }
}
