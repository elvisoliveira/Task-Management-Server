<?php

namespace App\Jobs;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotPasswordMail;
use Illuminate\Mail\Mailable;
class SendMail extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $email;
    public $mail;
    public function __construct(String $email, Mailable $mail)
    {
        $this->email = $email;
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send($this->mail);
    }
}
