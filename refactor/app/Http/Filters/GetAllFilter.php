<?php

namespace App\Http\Filters;
use Illuminate\Support\Facades\DB;

class GetAllFilter extends Filter
{
    protected $filters = ['feedback', 'id','lang','status','expired_at','will_expire_at','customer_email','translator_email','filter_timetype','job_type','physical','phone','flagged','distance','salary','count','consumer_type','booking_type'];

    public function feedback($value)
    {
        return $this->builder->where('ignore_feedback', '0')->whereHas('feedback', function ($q) {
            $q->where('rating', '<=', '3');
        });
    }

    public function count($value)
    {
        return $this->builder->count();
    }

    public function id($value)
    {
        if (is_array($value))
            return $this->builder->whereIn('id', $value);
        else
            return $this->builder->where('id', $value);

    }

    public function lang($value)
    {
        return $this->builder->whereIn('from_language_id', $value);
    }

    public function status($value)
    {
        return $this->builder->whereIn('status', $value);
    }

    public function expired_at($value)
    {
        return $this->builder->where('expired_at', '>=', $value);
    }

    public function will_expire_at($value)
    {
        return $this->builder->where('will_expire_at', '>=', $value);
    }

    public function customer_email($value)
    {
        $users = DB::table('users')->whereIn('email', $value)->get();
        if ($users) {
            return $this->builder->whereIn('user_id', collect($users)->pluck('id')->all());
        }
    }

    public function translator_email($value)
    {
        $users = DB::table('users')->whereIn('email', $value)->get();
        if ($users) {
            $allJobIDs = DB::table('translator_job_rel')->whereNull('cancel_at')->whereIn('user_id', collect($users)->pluck('id')->all())->lists('job_id');
            return $this->builder->whereIn('id', $allJobIDs);
        }
    }

    public function filter_timetype($value)
    {
       if($value == "created"){
            if (isset(request()->from) && request()->from != "") {
                return $this->builder->where('created_at', '>=', request()->from);
            }
            if (isset(request()->to) && request()->to != "") {
                $to = request()->to . " 23:59:00";
                return $this->builder->where('created_at', '<=', $to);
            }
            return $this->builder->orderBy('created_at', 'desc');
       }
       if($value == "due"){
            if (isset(request()->from) && request()->from != "") {
                return $this->builder->where('due', '>=', $requestdata["from"]);
            }
            if (isset(request()->to) && request()->to != "") {
                $to = request()->to . " 23:59:00";
                return $this->builder->where('due', '<=', $to);
            }
            return $this->builder->orderBy('due', 'desc');
       }
    }

    public function job_type($value)
    {
        return $this->builder->whereIn('job_type', $value);
    }

    public function physical($value)
    {
        return $this->builder->where('customer_physical_type', $value)->where('ignore_physical', 0);
    }

    public function phone($value)
    {
        return $this->builder->where('customer_phone_type', $value)->where('ignore_physical_phone', 0);
    }

    public function flagged($value)
    {
        return $this->builder->where('flagged', $value)->where('ignore_flagged', 0);
    }

    public function distance($value)
    {
        return $this->builder->whereDoesntHave('distance');
    }

    public function salary($value)
    {
        return $this->builder->whereDoesntHave('user.salaries');
    }

    public function consumer_type($value)
    {
        return $this->builder->whereHas('user.userMeta', function($q) {
            $q->where('consumer_type', request()->consumer_type);
        });
    }

    public function booking_type($value)
    {
        if ($value == 'physical')
            return $this->builder->where('customer_physical_type', 'yes');
        if ($requestdata['booking_type'] == 'phone')
            return $this->builder->where('customer_phone_type', 'yes');
    }
}
