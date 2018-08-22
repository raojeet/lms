<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Course;
use App\Models\Order;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PaymentController extends Controller
{
    public function s2s(Request $request)
    {
        $request = $request->all();
        $logger = new Logger('Request-Response');
        $file = 'logs/request_response-'.date('Y-m-d').'.log';
        $logger->pushHandler(new StreamHandler(storage_path($file)));
        $logMessage = ['request' => $request];
        $logger->info(var_export($logMessage, true));
        
        if($request['event'] == 'payment.captured') {
           $data =  $request['payload']['payment']['entity'];
            $user_data = [
               'first_name' => $data['notes']['name'],
               'last_name' => null,
               'email' => $data['notes']['email'],
               'phone' => $data['notes']['phone'],
               'password' => '123456',
            ];
            $user = $this->create($user_data);
            event(new Registered($user));
            $amount = $data['amount']/100;
            $course_name = $data['notes']['program_name'];
            $course = Course::where('title', 'LIKE', "%$course_name%")->first();
            if(empty($course)) {
                $course = Course::where('price', $data['amount'])->first();
            }
            // save order details
            $order = new Order();
            $order->user_id = $user->id;
            $order->reference_no = $data['order_id'];
            $order->amount = $amount;
            $order->status = 1;
            $order->coupon_id = 0;
            $order->payment_type = 4; // payent from razorpay
            $order->save();

            //Getting and Adding items
            $type = Course::class;
            $id = $course->id;
            $order->items()->create([
                'item_id' => $id,
                'item_type' => $type,
                'price' => 0
            ]);

            foreach ($order->items as $orderItem) {
                $orderItem->item->students()->attach($order->user_id);
            }
            
            $invoiceEntry = \App\Models\Invoice::where('order_id','=',$order->id)->first();
            if($invoiceEntry == ""){
                $invoiceEntry = new \App\Models\Invoice();
                $invoiceEntry->user_id = $order->user_id;
                $invoiceEntry->order_id = $order->id;
                $invoiceEntry->url = 'invoice-'.$order->id.'.pdf';
                $invoiceEntry->save();
            }
        }
    }
    
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $user = User::where('email',$data['email'])->first();
        if(empty($user)) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
                    $user->dob = isset($data['dob']) ? $data['dob'] : NULL ;
                    $user->phone = isset($data['phone']) ? $data['phone'] : NULL ;
                    $user->gender = isset($data['gender']) ? $data['gender'] : NULL;
                    $user->address = isset($data['address']) ? $data['address'] : NULL;
                    $user->city =  isset($data['city']) ? $data['city'] : NULL;
                    $user->pincode = isset($data['pincode']) ? $data['pincode'] : NULL;
                    $user->state = isset($data['state']) ? $data['state'] : NULL;
                    $user->country = isset($data['country']) ? $data['country'] : NULL;
                    $user->save();

            $userForRole = User::find($user->id);
            $userForRole->confirmed = 1;
            $userForRole->save();
            $userForRole->assignRole('student');
        }
        return $user;
    }

}
