<?php
namespace App\Services;
use DTApi\Repository\BookingRepository;
use DTApi\Helpers\TeHelper;
use Carbon\Carbon;
use Event;
use DTApi\Events\JobWasCreated;

class BookingService{

    public function __construct(BookingRepository $bookingRepo)
    {
        $this->bookingRepo = $bookingRepo;
    }
    
    public function store($user, $data)
    {
        $immediatetime = 5;
        $consumer_type = $user->userMeta->consumer_type;
        $response['status'] = 'fail';
        $response['message'] = "Translator can not create booking";
        $data['customer_phone_type'] = 'no';
        $data['customer_physical_type'] = 'no';
        $response['customer_physical_type'] = 'no';

        if ($user->user_type == env('CUSTOMER_ROLE_ID')) {
            $cuser = $user;

            if (!isset($data['from_language_id']))  {
                $response['field_name'] = "from_language_id";
                $response['message'] = "Du måste fylla in alla fält";
            }
            
            if (isset($data['duration']) && $data['duration'] == '') {
                $response['field_name'] = "duration";
                $response['message'] = "Du måste fylla in alla fält";
            }
        
            if (isset($data['customer_phone_type'])) 
                $data['customer_phone_type'] = 'yes';
            
            if (isset($data['customer_physical_type'])) {
                $data['customer_physical_type'] = 'yes';
                $response['customer_physical_type'] = 'yes';
            }
            $data['gender'] = 'female';
            if (in_array('male', $data['job_for'])) 
                $data['gender'] = 'male';
 
            $data = $this->checkcertified($data);
            switch($consumer_type){
                case('rwsconsumer'):
                    $data['job_type'] = 'rws';
                case('ngo'):
                    $data['job_type'] = 'unpaid';
                case('paid'):
                    $data['job_type'] = 'paid';
                default:
                    break;

            }
            $this->checkImmediate($data);

            if ($data['immediate'] == 'no') {
                $due = $data['due_date'] . " " . $data['due_time'];
                $response['type'] = 'regular';
                $due_carbon = Carbon::createFromFormat('m/d/Y H:i', $due);
                $data['due'] = $due_carbon->format('Y-m-d H:i:s');
                if ($due_carbon->isPast()) {
                    $response['message'] = "Can't create booking in past";
                }
            }
            $data['b_created_at'] = date('Y-m-d H:i:s');
            if (isset($due))
                $data['will_expire_at'] = TeHelper::willExpireAt($due, $data['b_created_at']);
            $data['by_admin'] = isset($data['by_admin']) ? $data['by_admin'] : 'no';

            $job = $this->bookingRepo->store($cuser,$data);
            $this->prepareDataforJob->prepareDataforJob($data,$job,$cuser);
            
        }
        return $response;
    }
    public function checkcertified($data)
    {
        switch($data){
            case(in_array('normal', $data['job_for'])):
                $data['certified'] = 'normal';
                break;
            case(in_array('certified', $data['job_for'])):
                $data['certified'] = 'yes';
                break;
            case(in_array('certified_in_law', $data['job_for'])):
                $data['certified'] = 'law';
                break;
            case(in_array('certified_in_helth', $data['job_for'])):
                $data['certified'] = 'health';
                break;
            default:
                $data['certified'] = '';
                break;
        }
        switch($data){
            case(in_array('normal', $data['job_for']) && in_array('certified', $data['job_for'])):
                $data['certified'] = 'both';
                break;
            case(in_array('normal', $data['job_for']) && in_array('certified_in_law', $data['job_for'])):
                $data['certified'] = 'n_law';
                break;
            case(in_array('normal', $data['job_for']) && in_array('certified_in_helth', $data['job_for'])):
                $data['certified'] = 'n_health';
                break;
            default:
                $data['certified'] ='';
                break;
        }

        return $data;
    }

    public function checkImmediate($data)
    {
        $response['message'] = "Du måste fylla in alla fält";
        $response['field_name'] = null;

        if ($data['immediate'] == 'yes') {
            $due_carbon = Carbon::now()->addMinute($immediatetime);
            $data['due'] = $due_carbon->format('Y-m-d H:i:s');
            $data['immediate'] = 'yes';
            $data['customer_phone_type'] = 'yes';
            $response['type'] = 'immediate';
        
        } else {
            if (isset($data['due_date']) && $data['due_date'] == '') 
                $response['field_name'] = "due_date";
                
            if (isset($data['due_time']) && $data['due_time'] == '') 
                $response['field_name'] = "due_time";
                
            if (!isset($data['customer_phone_type']) && !isset($data['customer_physical_type'])) {
                
                $response['message'] = "Du måste göra ett val här";
                $response['field_name'] = "customer_phone_type";
                
            }
            return $response;

        }
    }
    public function prepareDataforJob($data,$job,$cuser)
    {
        $response['status'] = 'success';
        $response['id'] = $job->id;
        $data['job_for'] = array();
        if ($job->gender != null) {
            if ($job->gender == 'male') {
                $data['job_for'][] = 'Man';
            } else if ($job->gender == 'female') {
                $data['job_for'][] = 'Kvinna';
            }
        }
        if ($job->certified != null) {
            if ($job->certified == 'both') {
                $data['job_for'][] = 'normal';
                $data['job_for'][] = 'certified';
            } else if ($job->certified == 'yes') {
                $data['job_for'][] = 'certified';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }

        $data['customer_town'] = $cuser->userMeta->city;
        $data['customer_type'] = $cuser->userMeta->customer_type;

        return $data;

    }

    public function storeJobEmail($data)
    {
        $result = $this->bookingRepo->storeJobEmail($data);
        $email = $result['user']->email;
        $name = $result['user']->name;
        if ($result['job']->user_email) {
            $email = $result['job']->user_email;
        } 
        
        $subject = 'Vi har mottagit er tolkbokning. Bokningsnr: #' . $result['job']->id;
        $send_data = [
            'user' => $result['user'],
            'job'  => $result['job']
        ];
        TeHelper::sendMail($email, $name, $subject,$send_data);

        $response['type'] = $user_type;
        $response['job'] = $result['job'];
        $response['status'] = 'success';
        $data = $this->bookingRepo->jobToData($result['job']);
        Event::fire(new JobWasCreated($result['job'], $data, '*'));
        return $response;
    }


}