<?php

namespace Logger\Monolog\Handler;

use DB;
use Illuminate\Support\Facades\Auth;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class MysqlHandler extends AbstractProcessingHandler
{
    protected $table;
    protected $connection;

    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        $this->table      = env('DB_LOG_TABLE', 'logs');
        $this->log_filter_table = env('DB_LOG_FILTER_TABLE','logs_filter');
        $this->connection = env('DB_LOG_CONNECTION', env('DB_CONNECTION', 'mysql'));

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        // User Info
        $userIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        
        // Get Filters
        $ipFilters = $this->_getFilterByType('IP');
        $userAgentFilters = $this->_getFilterByType('UserAgent');

        // Loop IP Filters
        foreach($ipFilters as $ipFilter) {
            if($userIP = $ipFilter->filter_content) return false;
        }
        // Loop UserAgent Filters
        foreach($userAgentFilters as $userAgentFilter) {
            if($userAgent = $userAgentFilter->filter_content) return false;
        }
        
        $data = [
            'instance'    => env('APP_URL','localhost'),
            'message'     => $record['message'],
            'channel'     => $record['channel'],
            'level'       => $record['level'],
            'level_name'  => $record['level_name'],
            'context'     => json_encode($record['context']),
            'remote_addr' => $userIP,
            'user_agent'  => $userAgent,
            'created_by'  => Auth::id() > 0 ? Auth::id() : null,
            'created_at'  => $record['datetime']->format('Y-m-d H:i:s')
        ];

        return DB::connection($this->connection)->table($this->table)->insert($data);
    }

    private function _getFilterByType($filterType) {

        return DB::connection($this->connection)
            ->table($this->log_filter_table)
            ->where('filter_type','=',$filterType)
            ->select('filter_type','filter_content')
            ->get();
    }
}
