<?php

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class MySQLHandler extends AbstractProcessingHandler
{
    protected $pdo;
    protected $table;

    public function __construct(PDO $pdo, string $table, array $options = [], $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record): void
    {
        $channel = $record['channel'];
        $level = $record['level_name'];
        $message = $record['message'];
        $context = json_encode($record['context']);
        $datetime = $record['datetime']->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (`channel`, `level`, `message`, `context`, `datetime`) VALUES (:channel, :level, :message, :context, :datetime)");
        $stmt->bindParam(':channel', $channel);
        $stmt->bindParam(':level', $level);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':context', $context);
        $stmt->bindParam(':datetime', $datetime);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error inserting log into MySQL: " . $e->getMessage());
        }
    }
}

?>