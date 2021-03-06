<?php
/**************************************************************************
This file is part of Magma.
Magma is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
Magma is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Lesser General Public License for more details.
You should have received a copy of the GNU Lesser General Public License
along with Magma. If not, see <http://www.gnu.org/licenses/>.
**************************************************************************/
namespace Inxk;

define("DS", DIRECTORY_SEPARATOR);

class Magma{

	private $db 	  = null;
	private $dir      = null;
	private $table 	  = null;
	private $fatal    = false;
	private $types    = ["INT","DATE","TIMESTAMP","TEXT","FLOAT"];
	private $log      = false;
	public  $autouse  = false;
	public  $debug    = false;


	/**
	 * Register string with OK or FAIL depend of $error
	 * @param (bool) $error 
	 * @param (string) $string
	 * @return (bool)
	 */
	private function log($error, $string=null){

		if(!$this->log)
			return false;

		if($this->dir){
			$log = $this->dir."logs";
			if(!is_dir($log))
				mkdir($log, 0777);
		}

		$date = date("Y/m/d H:i:s");

		if($error==true)
			$marker = "OK";
		else
			$marker = "FAIL";

		$debug = debug_backtrace();

		$debug = $debug[count($debug)-1];

		$args = json_encode($debug["args"], JSON_NUMERIC_CHECK);

		if($string){
			$string = $string;
		}else{
			$string = $debug["class"].'::'.$debug["function"].'('.$args.')';	
		}

		$format = "[$date] {{$marker}} $string\r\n";

		return file_put_contents($log.DS.'magma.log', $format, FILE_APPEND);
	}

	/**
	 * Make HTML Format errors
	 * @param (array) $e
	 * @return (string) 
	 */
	private function html_errors($e){
		if($e["TYPE"]=="FATAL"){
			$css = "padding:4px;font-family: Arial;background-color: #F00;";
		}elseif($e["TYPE"]=="WARNING"){
			$css = "padding:4px;font-family: Arial;background-color: #FF6600;";
		}elseif($e["TYPE"]=="NOTICE"){
			$css = "padding:4px;font-family: Arial;background-color: #008AFF;";
		}

		$backtrace = debug_backtrace();

		$file = $backtrace[count($backtrace)-1]['file'];
		$line = $backtrace[count($backtrace)-1]['line'];

		return "<div><span style=\"{$css}\">[{$e['TYPE']}] : {$e['MSG']} in {$file} on line {$line}</span></div>";
	}

	/**
	 * Manage exceptions errors (This don't generate trigger())
	 * @param (array) $e
	 * @return (bool)
	 */
	private function exception($e){

		$this->log(true, $e["MSG"]);

		if($this->debug==true){

			if($e["TYPE"]=="FATAL"){
				$this->fatal = true;
			}

			echo $this->html_errors($e);
			return true;
		}
		return false;
	}

	/**
	 * Append data and vars to the current table file
	 * @param (array) $data
	 * @param (array) $vars
	 * @return (bool)
	 */
	private function append($data, $vars=[]){

		if($this->read()){
			$content = $this->read();
		}

		if(!empty($vars)){
			$struct = $this->getStruct();

			foreach($vars as $k=>$v){
				$content->VARS->$k = $v;
			}	
		}

		if(isset($content->DATA) && !empty($content->DATA)){
			$content->DATA = array_merge((array) $content->DATA, $data);
		}else{
			$content->DATA = $data;
		}

		return $this->write($content);
	}

	/**
	 * Write data into table file
	 * @param (string) $data
	 * @return (bool)
	 */
	private function write($data){

		if(isset($data->DATA))
			$data->TOTAL = count($data->DATA);

		$data = json_encode($data, JSON_NUMERIC_CHECK);

		if(file_put_contents($this->dir.$this->db.DS.$this->table.'.table', $data, LOCK_EX)){
			return true;
		}

		return false;
	}

	private function read(){

		$filename = $this->dir.$this->db.DS.$this->table.'.table';

		if(!file_exists($filename))
			return $this->exception(["TYPE"=>"FATAL", "MSG"=>"Table {$this->table} don't exist"]);
		
		return json_decode(file_get_contents($filename));
	}


	/**
	 * Get structure of a table
	 * @return (object)
	 */
	private function getStruct(){

		$content = $this->read();

		$OBJ = new \stdClass();

		foreach($content as $k=>$v){
			$OBJ->$k = $v;
		}

		if(isset($content->DATA)){
			$OBJ->TOTAL = count($content->DATA);
		}else{
			$OBJ->TOTAL = 0;
		}

		return $OBJ;
	}

	/**
	 * Sort $data by $sort (column=>order)
	 * @param (array) $data		Data
	 * @param (array) $sort		Order (ASC or DESC) by the column ("id"=>"DESC") will be (5,4,3,2,1)
	 * @return (array) Return $data ordered by $sort, if $sort = NULL, $data is equaled to the return
	 */
	private function sortBy($data, $sort=NULL){

		$total = count($data);

		if($sort==NULL || $total===1){
			return $data;
		}else{

			$tmp = NULL;

			$keys = array_keys($sort);
			$key = $keys[0];
			$value = array_values($sort);

			if($value[0]=="DESC"){
				for($i = 0; $i < $total; $i++){
					for($j = $total-1; $j >= $i; $j--){ 
						if(isset($data[$j+1]) &&  ($data[$j+1]->$key > $data[$j]->$key)){ 
							$tmp = $data[$j+1]; 
							$data[$j+1] = $data[$j]; 
							$data[$j] = $tmp; 
						}
					} 
				}
			}elseif($value[0]=="ASC"){
				for($i = 0; $i < $total; $i++){
					for($j = $total-1; $j >= $i; $j--){ 
						if(isset($data[$j+1]) &&  ($data[$j+1]->$key < $data[$j]->$key)){ 
							$tmp = $data[$j+1]; 
							$data[$j+1] = $data[$j]; 
							$data[$j] = $tmp; 
						}
					} 
				}					
			}				
		}

		return $data;

	}

	/**
	 * Drop (TABLE OR DATABASE)
	 * @return (bool)
	 */
	private function drop($type="TABLE", $name=null){

		if($type=="TABLE"){
			$table = ($name) ? $name : $this->table;
			$filename = $this->dir.$this->db.DS.$table.'.table';
			return unlink($filename);
		}
		return false;
	}

	/**
	 * Emulate fonctions can't be named because of main function name (Support for PHP <= 5.5)
	 */
	public function __call($method, $args){
		if($method === 'new') {
			return call_user_func_array(array($this, '_new'), $args);
		}elseif($method === 'use'){
			return call_user_func_array(array($this, '_use'), $args);
		}else{
			throw new LogicException('Unknown method');
		}
	}

	/**
	 * Constructor init config vars
	 * @param (array) $vars
	 * @return (bool)
	 */
	public function __construct($vars=[]){

		if(!empty($vars) && is_array($vars)){
			foreach($vars as $k=>$v){
				if($this->$k==null){
					if($k=="dir"){
						$v = trim($v, DS).DS;
					}
					$this->$k = $v;
				}
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * Create new database (folder)
	 * @param   (string) $dbname
	 * @return (bool)
	 */	

	public function _new($dbname=null){

		if($this->fatal){
			return false;
		}

		if(!$dbname){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"Missing argument \$dbname"]);
			return false;
		}

		if(!is_dir('_'.$dbname)){
			mkdir('_'.$dbname, 0600);
			if($this->autouse==true){
				$this->use($dbname);
			}
			return true;
		}
		$this->exception(["TYPE"=>"FATAL", "MSG"=>"The database {$dbname} already exists"]);
		return false;
	}

	/**
	 * Load the database folder
	 * @param   (string) $dbname
	 * @return (bool)
	 */
	public function _use($dbname){

		if($this->fatal){
			return false;
		}

		if(is_dir('_'.$dbname)){
			$this->db = '_'.$dbname;
			return true;
		}
		return false;
	}

	/**
	 * Load the table file
	 * @param   (string) $table
	 * @return (bool)
	 */
	public function load($table){

		if($this->fatal){
			return false;
		}

		if(is_file($this->db.DS.$table.'.table')){
			$this->table = $table;
			return true;
		}
		$this->exception(["TYPE"=>"FATAL", "MSG"=>"The table {$table} don't exists"]);
		return false;
	}

	/**
	 * Create table with structure
	 * @param  (string) $name
	 * @param  (array) $structure
	 * @return (bool)
	 */
	public function create($name, $structure){

		if($this->fatal){
			return false;
		}

		if(isset($this->db)){

			$filename = $this->db.DS.$name.'.table';

			if(!file_exists($filename)){

				if(!preg_match('/([A-z0-9_\-\.]+)/', $name)){
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"Invalid table name : {$name}"]);
					return false;			
				}

				$OBJ = new \stdClass();

				foreach($structure as $column=>$options){

					$options = strtoupper(trim($options, ','));

					if(!preg_match('/([A-z0-9_\-\.]+)/', $column)){
						$this->exception(["TYPE"=>"FATAL", "MSG"=>"Invalid column name : {$column}"]);
						return false;			
					}

					$option = explode(',', $options);

					if(in_array($option[0], $this->types)){

						$OBJ->STRUCT[$column]["TYPE"] = $option[0];
						$OBJ->STRUCT[$column]["NAME"] = $column;

						array_shift($option);

						foreach($option as $v){
							$v = strtoupper($v);
							if($v=="AUTO_INCREMENT"){
								$OBJ->VARS[$v] = 1;
							}
							$OBJ->STRUCT[$column][$v] = true;
						}
					}else{
						$this->exception(["TYPE"=>"FATAL", "MSG"=>"Unknown type : {$option[0]}"]);
						return false;
					}
				}

				touch($filename);
				$this->table = $name;
				$this->write($OBJ);
				$this->table = null;

				return true;

			}else{
				$this->exception(["TYPE"=>"FATAL", "MSG"=>"The table {$name} already exists"]);
			}
		}else{
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"No database selected"]);
		}
		return false;
	}

	/**
	 * Fetch data with matching $conditions
	 * @param  (array) $conditions
	 * @param  (array) $options
	 * @return (object)
	 */
	public function fetch($conditions=[], $options=[]){

		if($this->fatal){
			return false;
		}

		if(!isset($this->db)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"No database selected"]);
			return false;
		}

		if(!isset($this->table)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"No table selected"]);
			return false;
		}

		$content = $this->read();

		if(isset($content->DATA))
			$content->DATA = (array) $content->DATA;

		if(!isset($content->DATA)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"The table {$this->table} is empty"]);
			return false;
		}

		if(isset($options["LIMIT"])){
			$LIMIT = explode(',', $options["LIMIT"]);
			if(isset($LIMIT[1])){
				$limit = $LIMIT[1];
				$offset = $LIMIT[0];
			}else{
				$limit = $options["LIMIT"];
				$offset = 0;
			}
		}


		if(isset($options["FIELDS"]) && is_array($options["FIELDS"]) && empty($conditions)){
			$alternate = [];
			foreach($content->DATA as $i=>$v){
				if(isset($offset) && isset($limit)){
					if($i>$offset+$limit-1){
						break;
					}
				}

				foreach($v as $k=>$w){
					if(in_array($k, $options["FIELDS"])){
						if(!isset($alternate[$i])){
							$alternate[$i] = new \stdClass();
						}
						$alternate[$i]->$k = $v->$k;
					}else{
						continue;
					}
				}
			}		
		}

		if(!empty($conditions)){
			foreach($content->DATA as $i=>$v){
				if(isset($offset) && isset($limit)){
					if($i>$offset+$limit-1){
						break;
					}
				}

				foreach($v as $k=>$w){
					if(isset($conditions[$k]) && !empty($options["FIELDS"])){
						foreach($conditions as $col=>$val){
							if($val==$w){
								foreach($v as $column=>$value){
									if(in_array($column, $options["FIELDS"])){
										if(!isset($alternate[$i])){
											$alternate[$i] = new \stdClass();
										}
										$alternate[$i]->$column = $v->$column;
									}else{
										continue;
									}	
								}
							}
						}
						break;
					}else{
						continue;
					}
				}
			}
		}

		if(isset($options["LIMIT"])){
			if(!isset($alternate)){
				foreach($content->DATA as $k=>$v){

					if($k<=$offset-1){
						continue;
					}

					if($k>$offset+$limit-1){
						break;
					}else{
						$alternate[$k] = $v;
					}
				}
			}else{
				foreach($alternate as $k=>$v){

					if($k<=$offset-1){
						continue;
					}

					if($k>$offset+$limit-1){
						break;
					}else{
						$alternate[$k] = $v;
					}
				}			
			}
		}

		if(isset($options["ORDER"]) && is_array($options["ORDER"])){
			if(isset($alternate)){
				$alternate = $this->sortBy($alternate, $options["ORDER"]);
			}else{
				$content->DATA = $this->sortBy($content->DATA, $options["ORDER"]);
			}
		}

		$this->log(true);

		if(!isset($alternate)){
			if(count($content->DATA)===1){
				return $content->DATA;
			}
			return $content->DATA;
		}else{
			if(count($alternate)===1){
				return $alternate;
			}
			return $alternate;
		}

	}

	/**
	 * Return the first occurence find
	 * @param (array) $conditions
	 * @return (object)
	 */
	public function find($conditions=[], $options=[]){
		$options["LIMIT"] = 1;
		return current($this->fetch($conditions, $options));
	}

	/**
	 * Insert data into table
	 * @param  (array) $data
	 * @return (bool)
	 */
	public function insert(){

		if($this->fatal){
			return false;
		}

		$datas = func_get_args();

		if(empty($datas)){
			$this->exception(["TYPE"=>"NOTICE", "MSG"=>"Nothing to insert, empty values"]);
			return false;			
		}
		
		$struct = $this->getStruct();

		$i=0;

		$columns = [];

		foreach($struct->STRUCT as $k=>$v){
			$columns[$i] = $v;
			$i++;
		}

		$content = "";


		$key = $struct->TOTAL;
		$data2 = [];

		foreach($datas as $data){
			foreach($data as $k=>$v){
				if(!isset($data2[$key]))
					$data2[$key] = new \stdClass;

				$col = $columns[$k]->NAME;

				/**
				 * DATA PROCESS HERE
				 */
				if($columns[$k]->TYPE=="INT" || $columns[$k]->TYPE=="TIMESTAMP"){
					if(!is_int($v) && !empty($v) && !intval($v)){
						$this->exception(["TYPE"=>"FATAL", "MSG"=>"Unexpected value : {$v} is not an {$columns[$k]->TYPE}"]);
						return false;
					}else{
						if($columns[$k]->TYPE=="INT" && empty($v) && isset($columns[$k]->AUTO_INCREMENT)){
							$data2[$key]->$col = $struct->VARS->AUTO_INCREMENT;
						}else{
							$this->exception(["TYPE"=>"FATAL", "MSG"=>"Unexpected null value"]);
							return false;								
						}
					}
				}elseif($columns[$k]->TYPE=="FLOAT"){
					if(!is_float($v) && !empty($v) && !floatval($v)){
						$this->exception(["TYPE"=>"FATAL", "MSG"=>"Unexpected value : {$v} is not an {$columns[$k]->TYPE}"]);
						return false;
					}						
				}elseif($columns[$k]->TYPE=="DATE" && !empty($v)){
					$data2[$key]->$col = date($v);
				}elseif($columns[$k]->TYPE=="DATE" && empty($v)){
					$data2[$key]->$col = date("Y-m-d");
				}else{
					$data2[$key]->$col = $v;
				}
			}

			if(isset($struct->VARS->AUTO_INCREMENT)){
				$struct->VARS->AUTO_INCREMENT++;
			}
			$key++;
		}

		if(isset($struct->VARS->AUTO_INCREMENT)){
			$this->append($data2, ["AUTO_INCREMENT"=>$struct->VARS->AUTO_INCREMENT]);
		}else{
			$this->append($data2);
		}

		return true;
	}

	/**
	 * Update data into table
	 * @param  (array) $data
	 * @param  (array) $conditions
	 * @return (bool)
	 */
	public function update($data = [], $conditions = []){

		if($this->fatal){
			return false;
		}

		if(empty($data)){
			$this->exception(["TYPE"=>"NOTICE", "MSG"=>"Nothing to update, missing argument 1"]);
			return false;			
		}
		
		$struct = $this->getStruct();

		if(empty($conditions)){
			foreach($struct->DATA as $k=>$v){
				foreach($v as $col=>$val){
					if(!isset($data[$col])){
						continue;
					}else{
						$struct->DATA[$k]->$col = $data[$col];
						continue;	
					}
				}
			}
		}else{
			if(!isset($struct->DATA))
				return false;

			foreach($struct->DATA as $k=>$v){
				foreach($v as $col=>$val){
					if(isset($conditions[$col])){
						if($conditions[$col]==$struct->DATA[$k]->$col){
							foreach($data as $key2=>$val2){
								$struct->DATA[$k]->$key2 = $val2;
								continue;
							}
						}
					}else{
						continue;
					}
				}
			}

			if(empty($struct->DATA)){
				$this->exception(["TYPE"=>"WARNING", "MSG"=>"The table {$this->table} is empty"]);
				return false;
			}		
		}

		if($this->write($struct)){
			return true;
		}
		return false;
	}

	/**
	 * Delete data into table
	 * @param  (array) $conditions
	 * @return (bool)
	 */
	public function delete($conditions = []){

		if($this->fatal){
			return false;
		}

		$struct = $this->getStruct();

		if(empty($conditions)){
			$struct->DATA = [];
			if($this->write($struct)){
				return true;
			}
			return false;
		}

		if(empty($struct->DATA) && !empty($conditions)){
			$this->exception(["TYPE"=>"WARNING", "MSG"=>"The table {$this->table} is empty"]);
			$struct->DATA = [];
		}

		$data = [];

		foreach($struct->DATA as $k=>$v){
			foreach($conditions as $col=>$value){
				if($value!=$v->$col){
					$modified = true;
					$data[] = $v;
					continue;
				}else{
					continue;
				}
			}
		}

		if(!$modified)
			return true;

		$struct->DATA = $data;

		$struct->TOTAL = count($data);

		if($this->write($struct)){
			return true;
		}
		return false;
	}

	/**
	 * Parse SQL query
	 * @param  (string) $query
	 * @return (object)
	 */
	public function query($query){

		if($this->fatal){
			return false;
		}

		$methods = [
			"fetch"  => "SELECT",
			"insert" => "INSERT",
			"update" => "UPDATE",
			"delete" => "DELETE"
		];

		$args = explode(' ', $query);

		if(!in_array($args[0], $methods)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, unexpected <i><b>$args[0]</b></i>"]);
			return false;	
		}

		$options = [];
		$conditions = [];

		/*=====================================================================
										SELECT
		======================================================================*/
		if($args[0]=="SELECT"){
			if($args[1]!='*'){
				$options["FIELDS"] = explode(',', $args[1]);
			}

			if(in_array("WHERE", $args)){

				$cond = strstr($query, "WHERE");

				if(preg_match_all('/([A-z0-9-_]+)=([A-z0-9-_]+)/', $cond, $matches)){
					array_shift($matches);
					foreach($matches[0] as $k=>$v){
						$conditions[$v] = $matches[1][$k];
					}
				}else{
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, invalid query <b><i>$query</i></b>"]);
					return false;
				}
			}

			if(in_array("LIMIT", $args)){

				$limit = strstr($query, "LIMIT");

				if(preg_match_all('/LIMIT ([0-9]+)|,([0-9]+)/', $limit, $matches)){
					array_shift($matches);
					if(empty($matches[1][1])){
						$options["LIMIT"] = $matches[0][0];
					}else{
						$options["LIMIT"] = $matches[0][0].','.$matches[1][1];
					}
				}else{
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, invalid query <b><i>$query</i></b>"]);
					return false;
				}
			}

			if(in_array("ORDER", $args) && in_array("BY", $args)){
				$order = strstr($query, "ORDER BY");

				if(preg_match_all('/ORDER BY ([A-z0-9-_]+) (ASC|DESC)/', $order, $matches)){
					array_shift($matches);
					$options["ORDER"][$matches[0][0]] = $matches[1][0];
				}else{
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, invalid query <b><i>$query</i></b>"]);
					return false;
				}
			}

			$this->load($args[3]);
			return $this->fetch($conditions, $options);
		}

		/*=====================================================================
										INSERT
		======================================================================*/
		elseif($args[0]=="INSERT"){

			/**
			 * Parse INSERT
			 */
			if(preg_match('/VALUES ?\((.*)\)/', $query, $match)){
				$rows = explode(", ", $match[1]);

				foreach($rows as $k=>$v){
					if($v==trim($v, '\'')){
						if($v==trim($v, '"')){
							$rows[$k] = trim($v, '"');
							continue;
						}else{
							continue;
						}
					}else{
						$rows[$k] = trim($v, '\'');
					}
				}
			}

			$this->load($args[2]);

			return $this->insert($rows);
		}

		/*=====================================================================
										UPDATE
		======================================================================*/
		elseif($args[0]=="UPDATE"){
			
			/**
			 * Parse UPDATE
			 */
			if(preg_match('/SET ?\((.*)\)/', $query, $lines)){

				$lines = explode(', ', $lines[1]);

				foreach($lines as $v){
					$explode = explode('=', $v);
					
					if($explode[0]==trim($explode[0], '\'')){
						if($explode[0]==trim($explode[0], '"')){
							$explode[0] = trim($explode[0], '"');
						}
					}else{
						$explode[0] = trim($explode[0], '\'');
					}					

					if($explode[1]==trim($explode[1], '\'')){
						if($explode[1]==trim($explode[1], '"')){
							$explode[1] = trim($explode[1], '"');
						}
					}else{
						$explode[1] = trim($explode[1], '\'');
					}

					$rows[$explode[0]] = $explode[1];

				}
			}

			$conditions = [];

			if(in_array("WHERE", $args)){

				$cond = strstr($query, "WHERE");

				if(preg_match_all('/([A-z0-9-_]+)=([A-z0-9-_]+)/', $cond, $matches)){
					array_shift($matches);
					foreach($matches[0] as $k=>$v){
						$conditions[$v] = $matches[1][$k];
					}
				}else{
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, invalid query <b><i>$query</i></b>"]);
					return false;
				}
			}

			$this->load($args[1]);

			return $this->update($rows, $conditions);			
		}

		/*=====================================================================
										DELETE
		======================================================================*/
		elseif($args[0]=="DELETE"){
			
			$conditions = [];

			if(in_array("WHERE", $args)){

				$cond = strstr($query, "WHERE");

				if(preg_match_all('/([A-z0-9-_]+)=([A-z0-9-_]+)/', $cond, $matches)){
					array_shift($matches);
					foreach($matches[0] as $k=>$v){
						$conditions[$v] = $matches[1][$k];
					}
				}else{
					$this->exception(["TYPE"=>"FATAL", "MSG"=>"<b>[SQL]</b> Syntax error, invalid query <b><i>$query</i></b>"]);
					return false;
				}
			}

			$this->load($args[2]);

			return $this->delete($conditions);			
		}
	}

	/**
	 * Drop table (erase table file)
	 * @param (string) $table The table to drop (default=current table($this->table))
	 * @return (bool)
	 */
	public function dropTable($table=null){
		if($this->fatal){
			return false;
		}

		if(!isset($this->table)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"No table selected"]);
			return false;
		}

		return $this->drop("TABLE", $table);
	}

	/**
	 * Count rows into current table
	 * @param (array) $conditions Conditions to match
	 * @param (array) $options Options
	 * @return (int) 
	 */
	public function count($conditions = [], $options = []){

		if($this->fatal){
			return false;
		}

		if(!isset($this->table)){
			$this->exception(["TYPE"=>"FATAL", "MSG"=>"No table selected"]);
			return false;
		}

		$data = $this->read();

		if(!isset($data->DATA))
			return (int) 0;

		if(empty($conditions) && empty($options))
			return count($data->DATA);
		else
			return count($this->fetch($conditions, $options));
	}

}
?>