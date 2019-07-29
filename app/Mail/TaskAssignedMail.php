<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $task;
    public $assigned_by;
    public $due_date;
    public function __construct($task, $due_date, $assigned_by)
    {
        $this->task = $task;
        $this->due_date = $due_date;
        $this->assigned_by = $assigned_by;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('taskAssigned')
                    ->subject('Task Assigned - VMock Task Management.');
    }
}