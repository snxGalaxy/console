<?php

class Console {
    const FG_DARK_BLACK = 30;
    const FG_DARK_RED = 31;
    const FG_DARK_GREEN = 32;
    const FG_DARK_YELLOW = 33;
    const FG_DARK_BLUE = 34;
    const FG_DARK_PURPLE = 35;
    const FG_DARK_OLIVE = 36;
    const FG_DARK_GREY = 37;
    const BG_DARK_BLACK = 40;
    const BG_DARK_RED = 41;
    const BG_DARK_GREEN = 42;
    const BG_DARK_YELLOW = 43;
    const BG_DARK_BLUE = 44;
    const BG_DARK_PURPLE = 45;
    const BG_DARK_OLIVE = 46;
    const BG_DARK_GREY = 47;
    const FG_BLACK = 90;
    const FG_RED = 91;
    const FG_GREEN = 92;
    const FG_YELLOW = 93;
    const FG_BLUE = 94;
    const FG_PURPLE = 95;
    const FG_OLIVE = 96;
    const FG_GREY = 97;
    const BG_BLACK = 100;
    const BG_RED = 101;
    const BG_GREEN = 102;
    const BG_YELLOW = 103;
    const BG_BLUE = 104;
    const BG_PURPLE = 105;
    const BG_OLIVE = 106;
    const BG_GREY = 107;
    const F_DEFAULT = 0;
    const F_BOLD = 1;
    const F_ITALIC = 3;
    const F_UNDERLINE = 4;
    const F_STRIKEOUT = 9;
    const F_HIGHLIGHT = 7;
    
    private static $console;
    private $std;
    private $fixedLine;
    private $progress;
    private $action;
    public $size;
    public $config;
    public $history;
    
    private function __construct()
    {
        $this->std = new stdClass();
        $this->std->in = fopen('php://stdin', 'r');
        $this->std->out = fopen('php://stdout', 'w');
        $this->fixedLine = null;
        $this->size = new stdClass();
        $this->size->width = exec('tput cols');
        $this->size->height = exec('tput lines');
        $this->config = new stdClass();
        $this->config->historyMaxCount = 50;
        $this->config->userInvitation = '> ';
        $this->config->answer = new stdClass();
        $this->config->answer->positive = ['', 'y', 'Y', 'yes', 'Yes', 'YES'];
        $this->config->answer->negative = ['n', 'N', 'no', 'No', 'NO'];
        $this->history = [];
        $this->progress = new stdClass();
        $this->action = new stdClass();
    }
    
    public static function getInstance()
    {
        if (!isset(static::$console)) {
            static::$console = new Console();
        }
        
        return static::$console;
    }
    
    private function directOutput($string, $isLog = true)
    {
        fwrite($this->std->out, $string);
        
        if ($isLog) {
            array_push($this->history, $string);
            
            while (count($this->history) > $this->config->historyMaxCount) {
                array_shift($this->history);
            }
        }
    }
    
    public function write($string, $format = null, $_ = null)
    {
        if (!empty($format)) {
            $string = call_user_func_array([$this, 'formatString'], func_get_args());
        }
        
        if (!empty($this->fixedLine)) {
            $this->eraseLine();
            $this->directOutput($string);
            
            if (substr($string, -1) != PHP_EOL) {
                $this->directOutput(PHP_EOL);
            }
            
            $this->directOutput($this->fixedLine);
        } else {
            $this->directOutput($string);
        }
    }
    
    public function writeLine($string, $format = null, $_ = null)
    {
        $args = func_get_args();
        $args[0] .= PHP_EOL;
        call_user_func_array([$this, 'write'], $args);
    }
    
    public function erase($length = 1)
    {
        $this->directOutput(str_repeat("\x08", $length) . str_repeat(' ', $length) . str_repeat("\x08", $length), false);
    }
    
    public function eraseLine()
    {
        $this->directOutput("\033[0G" . str_repeat(' ', $this->size->width) . "\033[0G");
    }
    
    public function read()
    {
        $string = str_split($this->readLine());
        
        return array_shift($string);
    }
    
    public function readLine()
    {
        return trim(fgets($this->std->in));
    }
    
    private function formatEscape($format)
    {
        if (!is_array($format)) {
            $format = [$format];
        }
        
        $result = '';
        
        foreach ($format as $f) {
            $result .= sprintf("\033[%dm", $f);
        }
        
        return $result;
    }
    
    public function formatString($string, $format, $_ = null)
    {
        $args = func_get_args();
        array_shift($args);
        
        return sprintf('%s%s%s', $this->formatEscape($args), $string, $this->formatEscape(self::F_DEFAULT));
    }
    
    public function setFormat($format, $_ = null)
    {
        $this->directOutput($this->formatEscape(func_get_args()), false);
    }
    
    public function resetFormat()
    {
        $this->directOutput($this->formatEscape(self::F_DEFAULT), false);
    }
    
    public function modal($string)
    {
        $this->write($string, self::F_BOLD);
        $this->write(' (Press Enter)', self::FG_GREEN);
        $this->read();
    }
    
    public function dialog($string)
    {
        $this->write($string, self::F_BOLD);
        $this->write(' (Y/n) ', self::FG_GREEN);
        $answer = $this->readLine();
        
        if (!in_array($answer, array_merge($this->config->answer->positive, $this->config->answer->negative))) {
            $this->writeLine('Please, answer \'Yes\' or \'No\'', self::F_ITALIC, self::FG_GREY);
            
            return $this->dialog($string);
        }
        
        return in_array($answer, $this->config->answer->positive);
    }
    
    public function prompt($string)
    {
        $this->writeLine($string, self::F_BOLD);
        $this->write($this->config->userInvitation);
        
        return $this->readLine();
    }
    
    public function sleep($seconds)
    {
        if ($seconds <= 0) {
            return;
        }
        
        $till = time() + $seconds;
        
        while (time() < $till) {
            $string = sprintf(' (%d)', $till - time());
            $this->directOutput($this->formatString($string, self::FG_GREY), false);
            sleep(1);
            $this->erase(strlen($string));
        }
    }
    
    public function progressStart($string, $totalCount)
    {
        $this->progress->name = $string;
        $this->progress->current = -1;
        $this->progress->total = $totalCount;
        
        $this->progressStep();
    }
    
    public function progressStep()
    {
        $percent = ++$this->progress->current / $this->progress->total * 100;
        
        if ($percent > 100) {
            $percent = 100;
        }
        
        $barWidth = $this->size->width - (strlen(strval($this->progress->total)) * 2 + strlen($this->progress->name) + 15);
        $barLength = $barWidth / 100 * $percent;
        $this->fixedLine = vsprintf('%s [%s>%s] - %d%% (%d/%d)', [
            $this->progress->name,
            str_repeat('=', ceil($barLength)),
            str_repeat(' ', floor($barWidth - $barLength)),
            $percent,
            $this->progress->current,
            $this->progress->total,
        ]);
        $this->eraseLine();
        $this->directOutput($this->fixedLine, false);
    }
    
    public function progressEnd()
    {
        $this->fixedLine = null;
    }
    
    public function actionStart($string)
    {
        $this->action->name = $string;
        $this->action->current = 0;
        $this->actionStep();
    }
    
    public function actionStep()
    {
        $this->eraseLine();
        $dots = str_repeat('.', $this->action->current++);
        $this->fixedLine = vsprintf('[%s%s]  %s', [
            $dots,
            str_repeat(' ', 3 - strlen($dots)),
            $this->action->name,
        ]);
        $this->directOutput($this->fixedLine, false);
        
        if ($this->action->current > 3) {
            $this->action->current = 1;
        }
    }
    
    public function actionEnd($isSuccess = true)
    {
        $this->eraseLine();
        $result = $isSuccess ? 'OK' : 'FAIL';
        $this->directOutput(vsprintf('[%s]%s%s', [
            $isSuccess ? $this->formatString($result, self::FG_GREEN) : $this->formatString($result, self::FG_RED),
            str_repeat(' ', 5 - strlen($result)),
            $this->action->name,
        ]), false);
        $this->fixedLine = null;
    }
}
