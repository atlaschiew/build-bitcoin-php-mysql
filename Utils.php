<?php

class Utils {
	
	static function arrayFind($xs, $f) {
		foreach ($xs as $x) {
			if (call_user_func($f, $x) === true)
			return $x;
		}
		return null;
	}
	
	static function cleanQueryString($removes) {
		
		$get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		
		if ($get) {
			foreach($removes as $remove) {
				unset($get[$remove]);
			}

			return http_build_query( $get );
		} else {
			return "";
		}
	}
	
	static function isValidDecimal($input) {
		
		$decimalPoint = Chain::AMOUNT_DECIMAL_POINT;
		$integerLen = Chain::AMOUNT_INTEGER_LEN;
		$totalLen = $decimalPoint + $integerLen + 1/* dot */;
		
		if (!preg_match('/^[0-9]{1,'.$integerLen.'}(\.[0-9]{1,'.$decimalPoint.'})?$/', $input)) {
			return false;
		}
		
		return true;
		
	}
	
	static function leftPadding($hexStr, $len) {
		return str_pad($hexStr, $len, "0", STR_PAD_LEFT);
	}
	
	static function bchexdec($hex) {
        if(strlen($hex) == 1) {
            return hexdec($hex);
        } else {
            $remain = substr($hex, 0, -1);
            $last = substr($hex, -1);
            return bcadd(bcmul(16, self::bchexdec($remain)), hexdec($last));
        }
    }

    static function bcdechex($dec) {
		
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);

        if($remain == 0) {
            return dechex($last);
        } else {
            return self::bcdechex($remain).dechex($last);
        }
    }
	
	
	static function printOut($content) {
		echo "[".date("Y-m-d H:i:s")."] " . $content . "\n";
	}

	static function config($key) {
		return $key ? $GLOBALS['config'][$key] : $GLOBALS['config'];
	}
	
	static function world($key = null, $value = null) {
		
		if($value!==null) {
			$GLOBALS['world'][$key] = $value;
		}
		
		return $key !== null ? $GLOBALS['world'][$key] : $GLOBALS['world'];
	}
	
	static function stringEach($value) {
		return (string)$value;
	}

	static function stringDeep($value) {
		return (is_array($value)) ? array_map("self::stringDeep", $value) : self::stringEach($value);
	}

	static function jsonDecode($var) {
		return json_decode($var, true);
	}
	
	static function jsonEncode($var) {
		$var = json_encode($var);
		$var = self::jsonDecode($var);
		$var = self::stringDeep($var);
		$var = json_encode($var);
		return $var;
	}

	static function safeSub($a, $b) {
		
		return bcsub($a,$b,Chain::AMOUNT_DECIMAL_POINT);
	}

	static function safeAdd($a, $b) {
		
		return bcadd($a,$b,Chain::AMOUNT_DECIMAL_POINT);
	}

	static function safeMul($a, $b) {
		
		return bcmul($a,$b,Chain::AMOUNT_DECIMAL_POINT);
	}

	static function safeDiv($a, $b) {
		
		return bcdiv($a,$b,Chain::AMOUNT_DECIMAL_POINT);
	}

	static function safeComp($a, $b) {
		
		return (int)(bccomp($a,$b,Chain::AMOUNT_DECIMAL_POINT));
	}
	
	static function safeMin($a,$b) {
		if (Utils::safeComp($a,$b)>=0) {
			return $b;
		} else {
			return $a;
		}
	}
	
}

class DB {
	public static $conn = null;
	public static $host = null;
	public static $user = null;
	public static $pass = null;
	public static $dbname = null;
	
	public static function connect($host, $user, $pass, $dbname = null) {
		
		self::$host = $host;
		self::$user = $user;
		self::$pass = $pass;
		self::$dbname = $dbname;
		
		self::$conn = mysqli_connect($host, $user, $pass, $dbname);
		if (mysqli_connect_errno()) {
			$errmsg .= ("Failed to connect to MySQL: " . mysqli_connect_error());
		} else if (!mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)) {
			$errmsg .= ("Failed to set mysqli report");
		} else if (!mysqli_autocommit(self::$conn, TRUE)) {
			$errmsg .= ("Failed to set mysqli autocommit");
		} else if (!mysqli_set_charset(self::$conn,"utf8")) {
			$errmsg .= ("Failed to set charset");
		}
		
		if ($errmsg) {
			throw new mysqli_sql_exception($errmsg);
		}
	}
	
	public static function close() {
		mysqli_close(self::$conn);
	}
	
	
	public static function result($res,$row=0,$col=0){ 
		$numrows = mysqli_num_rows($res); 
		if ($numrows && $row <= ($numrows-1) && $row >=0){
			mysqli_data_seek($res,$row);
			$resrow = (is_numeric($col)) ? mysqli_fetch_row($res) : mysqli_fetch_assoc($res);
			if (isset($resrow[$col])){
				return $resrow[$col];
			}
		}
		return false;
	}
	
	public static function esc($str) {
		return mysqli_real_escape_string(self::$conn,$str);
	}
	
	public static function errno() {
		$errno = mysqli_errno(self::$conn);
		return $errno;
	}
	
	public static function error($sql) {
		$err = mysqli_error(self::$conn);
		if ($err) {
			return "{$sql}, Error: {$err}";
		} else {
			return "";
		}
	}
	
	public static function beginTransaction() {
		mysqli_begin_transaction(self::$conn);
	}
	
	public static function rollback() {
		mysqli_rollback(self::$conn);
	}
	
	public static function commit() {
		mysqli_commit(self::$conn);
	}
	
	public static function affectedRows() {
		$r = mysqli_affected_rows(self::$conn);
		return $r;
	}
	
	public static function query($sql) {
		
		try {
			$r = mysqli_query(self::$conn,$sql);
		} catch(mysqli_sql_exception $e) {
			$errno = self::errno();
			
			if ($errno == 2006 or $errno == 2013 ) {
				try {
					self::connect(self::$host, self::$user, self::$pass, self::$dbname);
					$r = mysqli_query(self::$conn,$sql);
				} catch(Exception $e) {
					throw new mysqli_sql_exception($e->getMessage()." (Caught Mysqli Errno: {$errno})");
				}
			} else {
				throw new mysqli_sql_exception($e->getMessage()." (Caught Mysqli Errno: {$errno})");
			}
		}
		return $r;
	}
	
	public static function insertID() {
		$insertId = mysqli_insert_id(self::$conn);

		return $insertId;
	}
}