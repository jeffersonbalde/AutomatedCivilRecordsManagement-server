<?php
namespace App\Mail;

use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $staff;
    public $tempPassword;

    public function __construct(Staff $staff, $tempPassword)
    {
        $this->staff = $staff;
        $this->tempPassword = $tempPassword;
    }

    public function build()
    {
        return $this->subject('Your Staff Account Has Been Created')
                    ->markdown('emails.staff.account-created')
                    ->with([
                        'staff' => $this->staff,
                        'tempPassword' => $this->tempPassword,
                        'loginUrl' => url('/')
                    ]);
    }
}