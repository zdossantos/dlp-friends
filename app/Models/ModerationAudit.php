<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_user_id', 'target_user_id', 'conversation_report_id', 'operation', 'reason'])]
class ModerationAudit extends Model {}
