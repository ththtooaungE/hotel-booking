<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequest;
use App\Mail\BookingNotificationMail;
use App\Models\Reservation;
use App\Models\Room;
use App\Reporting\DashboardReporting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if(request()->has("from_date") || request()->has("to_date")){
            $rooms = Room::search(request(['from_date', 'to_date']))->get();
            $searchRooms = [];
            //avaiable rooms but only one room for a particular room-type
            foreach ($rooms as $room) {
                if(!isset($searchRooms[$room->room_type_id])) $searchRooms[$room->room_type_id] = $room;
            }
            return Inertia::render('Welcome',[
                'searchRooms' => $searchRooms
            ]);
        }
        return Inertia::render("Welcome");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ReservationRequest $request)
    {
        $from_date = date('Y-m-d', strtotime($request->from_date));
        $to_date = date('Y-m-d', strtotime($request->to_date));

        $report = new DashboardReporting();
        $availableRooms = $report->availableRooms($from_date, $to_date);

        $total_price = 0;

        foreach($request->room_id as $room) {
            if($availableRooms->find($room)) {
                $total_price += $availableRooms->find($room)->price;
            } else {
                throw ValidationException::withMessages(['room_id' => "Some of the rooms are reserved on given date."]);
            }
        }

        DB::beginTransaction();

        try {
            $reservation = new Reservation();
            $reservation->user_id = Auth::user()->id;
            $reservation->guest_name = $request->guest_name;
            $reservation->total_person = $request->total_person;
            $reservation->total_price = $total_price;
            $reservation->from_date =date('Y-m-d',strtotime($request->from_date));
            $reservation->to_date = date('Y-m-d',strtotime($request->to_date));
            $reservation->save();
            // ddd($reservation);
            $reservation->rooms()->attach($request->room_id);

            Cache::flush();

            DB::commit();
            Mail::to(Auth::user()->email)->send(new BookingNotificationMail($reservation));
            return redirect()->back();

        } catch (Exception $e) {
            DB::rollBack();
        }
    }


}
