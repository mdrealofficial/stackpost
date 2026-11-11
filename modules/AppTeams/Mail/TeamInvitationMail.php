<?php

namespace Modules\AppTeams\Mail;

use Illuminate\Mail\Mailable;
use Modules\AppTeams\Models\TeamInvitation;

class TeamInvitationMail extends Mailable
{
    public function __construct(public TeamInvitation $invitation) {}

    public function build(): static
    {
        return $this->subject(__('Invitation to :team', ['team' => $this->invitation->team->name]))
            ->view('appteams::mail.invitation');
    }
}
