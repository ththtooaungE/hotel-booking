<?php

namespace App\Reporting;

use App\Models\Reservation;
use App\Models\Room;

class DashboardReporting {

    public function popularRoomTypes($start_date, $end_date) : array {
        
        $reservations = Reservation::whereBetween('from_date',[$start_date,$end_date])
                        ->get();
        //calculating the times of booking a specific room type
        $room_types = [];
        foreach ($reservations as $reservation) {
            $rooms = $reservation->rooms;
            foreach ($rooms as $room) {
                $room_type = $room->roomType;
                if (!isset($room_types[$room_type->name])) $room_types[$room_type->name] = 1;
                else $room_types[$room_type->name] ++;
            }
        }
        return $room_types;
    }

    public function availableRooms($from_date = 0, $to_date = 0) : object {
        //get reservations of today
        $today = date('Y-m-d', strtotime('today'));
        $from_date = $from_date ? date('Y-m-d', strtotime($from_date)) : $today;
        $to_date = $to_date ? date('Y-m-d', strtotime($to_date)) : $today;

        $availableRooms = Room::whereDoesntHave('reservations', function($query) use ($from_date, $to_date) {
            $query->whereBetween('from_date',[$from_date, $to_date])
            ->orWhereBetween('to_date', [$from_date, $to_date])
            ->orWhere('from_date', '=', $from_date)
            ->orWhere('from_date', '=', $to_date)
            ->orWhere('to_date', '=', $from_date)
            ->orWhere('to_date', '=', $to_date)
            //check if the reservation date is between from_date and to
            ->orWhere(function ($query) use ($from_date, $to_date) {
                $query->where('from_date', '<=', $from_date)
                    ->where('to_date', '>=', $to_date);
            });
        })->get(); 
        return $availableRooms;
    }

    //reserved rooms for today
    public function reservedRooms() : object {
        $today = date('Y-m-d', strtotime('today'));
        $reservedRooms = Room::whereHas('reservations', function($query) use ($today) {
            $query->where('from_date', '=', $today);
        })->get();
        return $reservedRooms;
    }

    public function availableRoomTypes($from_date = 0, $to_date = 0) : array { //[roomtype => numberOfRooms]
        //getting availble rooms first
        $availableRooms = $this->availableRooms($from_date, $to_date);

        //calculate the numbers of available rooms depending on a particular room type
        $availableRoomTypes = [];
        foreach ($availableRooms as $availableRoom) {
            if(!isset($availableRoomTypes[$availableRoom->roomType->name])) $availableRoomTypes[$availableRoom->roomType->name] = 1;
            else $availableRoomTypes[$availableRoom->roomType->name] ++;
        }
        return $availableRoomTypes;
    }

    public function totalRooms() : int {
       return Room::all()->count();
    }
}
