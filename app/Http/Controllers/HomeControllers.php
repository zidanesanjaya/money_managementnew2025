<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use DB;
use Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class HomeControllers extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        return view('dashboard');
    }

    public function update_profile(Request $request)
    {
        $username = $request->username;
        $full_name = $request->full_name;
        $password = $request->password;
        $data = DB::table('users')->where('username', $username)->first();

        $username_now = Auth::user()->username;
        if ($data && $data->username != $username_now) {
            return back()->with('error', 'Gagal Update Data , Username Sudah Ada');
        } else {
            if ($password && $password != null && $password != '') {
                DB::table('users')->where('id', Auth::user()->id)->update([
                    'name' => $full_name,
                    'username' => $username,
                    'password' => md5($password),
                ]);
            } else {
                DB::table('users')->where('id', Auth::user()->id)->update([
                    'name' => $full_name,
                    'username' => $username,

                ]);
            }
        }

        return back()->with('success', 'Berhasil Update Profile');
    }

    public function post_transaction(Request $request)
    {
        DB::beginTransaction();
        try {
            DB::table('transaction')->insert([
                'id_user' => $request->id_user,
                'amount' => $request->amount,
                'type' => $request->type,
                'category' => $request->category,
                'note' => $request->note,
                'date' => $request->date
            ]);
            DB::commit();
            return redirect('/')->with('success', 'Berhasil Tambahkan Data');
        } catch (Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal Tambahkan Data');
        }
    }
    public function update_transaction(Request $request)
    {
        DB::beginTransaction();
        try {
            DB::table('transaction')->where('id', $request->id)->where('id_user', $request->id_user)->update([
                'amount' => $request->amount,
                'type' => $request->type,
                'category' => $request->category,
                'note' => $request->note,
                'date' => $request->date
            ]);
            DB::commit();
            return redirect('/')->with('success', 'Berhasil Update Data');
        } catch (Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal Update Data');
        }
    }

    public function delete_transaction($id)
    {
        DB::table('transaction')->where('id', $id)->delete();
        return back()->with('success', 'Berhasil Hapus Data');
    }

    public function dashboard_page(Request $request)
    {
        $user_id = Auth::user()->id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $where = '';
        if ($start_date && $end_date) {
            $where = "AND date BETWEEN '$start_date' AND '$end_date'";
        }
        $perPage = 10;

        $query = DB::select("
            SELECT
    date,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN type = 'outcome' THEN amount ELSE 0 END) AS outcome,
    
    -- Ganti JSON_ARRAYAGG dengan GROUP_CONCAT untuk membuat string JSON
    GROUP_CONCAT(
        JSON_OBJECT(
            'id', id,
            'note', note,
            'type', type,
            'amount', amount,
            'category', category
        )
    SEPARATOR ', ') AS data_user_string 
    
FROM transaction
WHERE id_user = $user_id $where
GROUP BY date 
ORDER BY date DESC;
        ");

        $result = collect($query)->map(function ($item) {

            // Ambil nilai dari alias SQL yang benar
            $json_string = $item->data_user_string;

            // Tambahkan tanda kurung siku ([]) secara manual untuk membuat string JSON yang valid
            $json_string_final = "[" . $json_string . "]";

            return [
                'date' => $item->date,
                'income' => $item->income,
                'outcome' => $item->outcome,
                // Gunakan $json_string_final yang sudah benar
                'data_user' => json_decode($json_string_final, true),
            ];
        })->toArray();

        $currentPage = Paginator::resolveCurrentPage();
        $data = new LengthAwarePaginator(
            array_slice($result, ($currentPage - 1) * $perPage, $perPage),
            count($result),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );


        $summary = DB::SELECT(
            "SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                    SUM(CASE WHEN type = 'outcome' THEN amount ELSE 0 END) AS outcome,
                    CASE 
                        WHEN SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'outcome' THEN amount ELSE 0 END) < 0 
                        THEN 0 
                        ELSE SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'outcome' THEN amount ELSE 0 END) 
                    END AS total
                FROM transaction 
                WHERE id_user = $user_id;
            "
        )[0];

        return view('dashboard', ['data' => $data, 'summary' => $summary]);
    }
}
