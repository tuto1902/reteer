<?php

namespace App\Console\Commands;

use Google\Client;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Google\Service\Sheets;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

class GetSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Return values from google sheet through API call';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = new Client();
        $client->setApplicationName('reteer-app');
        $client->setScopes('https://www.googleapis.com/auth/spreadsheets');
        $client->setAuthConfig(base_path('credentials.json'));

        $spreadsheet = new Sheets($client);
        $spreadsheetValues = $spreadsheet->spreadsheets_values;

        $rawData = $spreadsheetValues->get(config('sheets.id'), config('sheets.names.tasks'))->getValues();

        $header = Arr::map($rawData[0], function (string $item) {
            return Str::lower(str_replace(["(", ")", "*"], '', str_replace([" ", "\n", "__"], "_", $item)));
        });
        $headerLookup = Arr::map($header, function (string $item, $i) {
            return [$item => $i];
        });

        // $updatedValues = [$header]; TODO refactor to seperate function in Service Class
        $stagedValues = collect($rawData)
            ->skip(1)
            ->map(function ($taskValues) use ($header) {
                if (count($header) - count($taskValues) >= 0) {
                    $newArray = array_fill(count($taskValues), (count($header) - count($taskValues)), null);
                    $taskArray = array_combine($header, array_merge($taskValues, $newArray));
                } else {
                    return $taskValues;
                }
                return $taskArray;
            })
            ->map(function ($taskValues, $i) use ($headerLookup) {
                $raw_user = substr($taskValues['id_-_do_not_edit'], 0, -14);
                $date_of_appointment = Carbon::parse($taskValues['date_of_appointment']);
                $newRow = array_fill(0, count($taskValues), '');
                // FOR REFERENCE 
                // $desired_values = [
                //     'date_of_appointment' => 'start_date',
                //     'time' => 'start_time',
                //     'client_address' => 'client_address',
                //     'task_description' => 'task_description',
                //     'destination' => 'task_destination',
                //     'volunteer_or_case_manager' => 'volunteer_id',
                //     'task_hours' => '',
                //     'task_mileage' => '',
                //     'status' => 'status',
                //     'contact_information' => 'contact_information',
                //     'id_-_do_not_edit' => 'sheets_id',
                //     'app_user_name' => 'sheets_user',
                //     'row_number' => 'sheets_row',
                // ];
                // 
                foreach ($headerLookup as $index => $value) {
                    dump($taskValues);
                    dump("key " . $index);
                    $column_name = strval(key($value));
                    dump("value " . $column_name);
                    $realIndex = $taskValues[$column_name];
                    if (isset($headerLookup[$column_name])) {
                        $newRow[$column_name] = $taskValues[$column_name];
                    };
                };
                dump('NEW ROW');
                dump($newRow);
                // $newRow[$headerLookup['date_of_appointment']] = $date_of_appointment->toRfc7231String();
                // $newRow[$headerLookup['time']] = $taskValues['time'];
                // $newRow[$headerLookup['']] = $taskValues[''];
                return [
                    'sheets_id' => $taskValues['id_-_do_not_edit'] == '' ? "app" . Str::uuid() : $taskValues['id_-_do_not_edit'],
                    'sheets_row' => $i + 1,
                    'author' => $raw_user,
                    'sheets_created_at' => substr($taskValues['id_-_do_not_edit'], -14),
                    'start_date' => $date_of_appointment,
                    'start_time' => $taskValues['time'],
                    'public' => false,
                    'client_address' => $taskValues['client_address'],
                    'task_description' => $taskValues['task_description'],
                    'destination' => $taskValues['destination'],
                    'volunteer_id' => $taskValues['volunteer_or_case_manager'],
                    'status' => $taskValues['status'],
                    'contact_information' => $taskValues['contact_information'],
                ];
            })
            ->filter(function (array $row) {
                return $row['task_description'] ?? '' != '';
            });
        // dump($stagedValues);

        Task::query()->upsert($stagedValues->all(), 'sheets_id', [
            'sheets_row',
            'author',
            'start_date',
            'start_time',
            'client_address',
            'task_description',
            'destination',
            'volunteer_id',
            'status',
            'contact_information',
        ]);
    }
}
