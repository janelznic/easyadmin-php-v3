<?php
/**
 * @name mysqli
 * @version 2.0
 * @description Simply ORM pro práci s MySQL databází
 * @depends phpmini (>= 1.0)
 * @branch unstable
 */
class MySQL
{
	/**
	 * Constructor
	 */
	function __construct() {
		$this->session = null;
	}

	/**
	 * Připojí se k databázi
	 * @param {string} server Server
	 * @param {string} username Uživatel
	 * @param {string} password Heslo
	 */
	public function connect($server, $username, $password) {
		$this->session = mysqli_connect($server, $username, $password);

		if (mysqli_connect_errno()) {
			return false;
		}

		return true;
	}

	/**
	 * Vybere databázi
	 * @param {string} database Název databáze
	 */
	public function selectDB($database) {
		return mysqli_select_db($this->session, $database);
	}

	/**
	 * Ošetří řetězec pro použití v MySQL
	 * @param {string} string řetězec
	 */
	public function mres($string) {
		return mysqli_real_escape_string($this->session, $string);
	}

	/**
	 * Aktualizuje data v řádku na základě předaných informací
	 * @param {string} tabName Jméno tabulky
	 * @param {string|array} colName Název sloupce nebo pole sloupců, který/é budeme hledat
	 * @param {string|boolean} valueBy Hodnota ve sloupci, kterou budeme hledat; pokud hledáme více hodnot, tak false
	 * @param {array} data Nová data pro uložení
	 */
	public function updateRow($tabName, $colName, $valueBy, $data) {
		$tabName = $this->mres($tabName);

		$settings = "";
		$i=0;
		$colsCount = count($data)-1;
		foreach ($data as $key => $value) {
			$settings .= sprintf("%s = '%s'", $this->mres($key), $this->mres($value));
			if ($i != $colsCount) {
				$settings .= ", ";
			}
			$i++;
		}

		if (is_array($colName)) {
			$where = "";
			$j=0;
			$whereCount = count($colName)-1;
			foreach ($colName as $key => $value) {
				$where .= sprintf("%s like '%s'", $this->mres($key), $this->mres($value));
				if ($j != $whereCount) {
					$where .= " and ";
				}
				$j++;
			}
		} else {
			$colName = $this->mres($colName);
			$valueBy = $this->mres($valueBy);
			$where = sprintf("%s like '%s'", $colName, $valueBy);
		}

		$request = mysqli_query(
			$this->session,
			$sql = sprintf(
				"update %s_%s set %s where %s",
				Config::get("mysqlPrefix"), $tabName, $settings, $where
			)
		);

		if ($request) {
			return true;
		} else {
			Dbg::log("Error: Cannot update this row");
			return false;
		}
	}

	/**
	 * Načte řádek z DB
	 * @param {string} tabName Jméno tabulky
	 * @param {string} colName Název sloupce
	 * @param {string} value Hodnota ve sloupci, kterou budeme hledat
	 * @param {array} order Řazení sloupců (dle posloupnosti), může být false - nepovinné
	 */
	public function selectRow($tabName, $colName, $value = false, $order = false) {
		if (!$this->orderByControl($order)) return false;

		# Řazení podle sloupců
		$sort = $this->makeSorting($order);

		$tabName = $this->mres($tabName);

		if (is_array($colName)) {
			$where = "";
			$j=0;
			$whereCount = count($colName)-1;
			foreach ($colName as $key => $value) {
				if (!$j) $countCol = $key;
				$where .= sprintf("%s like '%s'", $this->mres($key), $this->mres($value));
				if ($j != $whereCount) {
					$where .= " and ";
				}
				$j++;
			}
		} else {
			$where = sprintf("%s like '%s'", $this->mres($colName), $this->mres($value));
		}

		list($count) = mysqli_fetch_row(mysqli_query(
			$this->session,
			$sql = sprintf(
				"select count(%s) from %s_%s where %s",
				(is_array($colName) ? $countCol : $colName), Config::get("mysqlPrefix"), $tabName, $where
			)
		));

		if (!$count) return false;
		$request = mysqli_query(
			$this->session,
			$sql = sprintf("select * from %s_%s where %s", Config::get("mysqlPrefix"), $tabName, $where)
		);

		if ($request) {
			$colsData = mysqli_fetch_array($request);

			$i = 0;
			foreach ($colsData as $key => $value) {
				if ($key == $i) {
					$i++;
				} else {
					$output[$key] = $value;
				}
			}
			return $output;
		} else {
			Dbg::log("Error: Cannot select this row");
			return false;
		}
	}

	/**
	 * Vrátí počet záznamů odpovídajcích dané hodnotě
	 * @param {string} tabName Jméno tabulky
	 * @param {string|array} colName Název sloupce nebo sloupců, podle kterých budeme hledat
	 * @param {string|boolean} value Hodnota ve sloupci, kterou budeme hledat; v případě více hodnot bude false
	 */
	public function countRows($tabName, $colName, $value) {
		$tabName = $this->mres($tabName);

		if (is_array($colName)) {
			$where = "";
			$j=0;
			$whereCount = count($colName)-1;
			foreach ($colName as $key => $value) {
				if (!$j) $countCol = $key;
				$where .= sprintf("%s like '%s'", $this->mres($key), $this->mres($value));
				if ($j != $whereCount) {
					$where .= " and ";
				}
				$j++;
			}
		} else {
			$where = sprintf("%s like '%s'", $this->mres($colName), $this->mres($value));
		}

		list($count) = mysqli_fetch_row($query = mysqli_query(
			$this->session,
			$sql = sprintf(
				"select count(%s) from %s_%s where %s",
				(is_array($colName) ? $countCol : $colName), Config::get("mysqlPrefix"), $tabName, $where
			)
		));

		if ($query) {
			return $count;
		} else {
			Dbg::log("Error: Cannot select rows count");
			return false;
		}
	}

	/**
	 * Smaže řádek z DB
	 * @param {string} tabName Jméno tabulky
	 * @param {string|array} colName Název sloupce nebo sloupců, podle kterého budeme mazat; případně hodnoty v něm
	 * @param {string|boolean} value Hodnota ve sloupci, kterou budeme hledat; v případě více porovnávacích sloupců a hodnot bude false
	 */
	public function deleteRow($tabName, $colName, $value) {
		$tabName = $this->mres($tabName);

		if (is_array($colName)) {
			$where = "";
			$i=0;
			$whereCount = count($colName)-1;
			foreach ($colName as $key => $value) {
				if (!$i) $countCol = $key;
				$where .= sprintf("%s like '%s'", $this->mres($key), $this->mres($value));
				if ($i != $whereCount) {
					$where .= " and ";
				}
				$i++;
			}
		} else {
			$where = sprintf("%s like '%s'", $this->mres($colName), $this->mres($value));
		}

		$request = mysqli_query(
			$this->session,
			$sql = sprintf(
				"delete from %s_%s where %s",
				Config::get("mysqlPrefix"),
				$tabName,
				$where
			)
		);

		if ($request) {
			return true;
		} else {
			Dbg::log("Error: Cannot delete row");
			return false;
		}
	}

	/**
	 * Vloží záznam do vybrané tabulky předáním pole hodnot
	 * @param {string} tabName Jméno tabulky
	 * @param {array} colsData Pole, kde klíč i hodnota mohou být string
	 */
	public function insertRow($tabName, $colsData) {
		if (!is_array($colsData)) {
			Dbg::log("Error: Argument colsData must be an array");
			return false;
		}

		$tabName = $this->mres($tabName);

		$colNames = "";
		$values = "";
		$i = 0;
		$colsCount = count($colsData)-1;
		foreach ($colsData as $key => $value) {
			$colNames .= $this->mres($key);
			$values .= sprintf("'%s'", $this->mres($value));
			if ($i != $colsCount) {
				$colNames .= ", ";
				$values .= ", ";
			}
			$i++;
		}

		$request = mysqli_query(
			$this->session,
			$sql = sprintf(
				"insert into %s_%s (%s) values (%s)",
				Config::get("mysqlPrefix"), $tabName, $colNames, $values
			)
		);

		if ($request) {
			return true;
		} else {
			Dbg::log(sprintf("Error: Cannot insert new row to table %s", $tabName));
			return false;
		}
	}

	/**
	 * Zkontroluje, zda-li je posloupnost řazení předávána jako pole
	 * @param {array} order Pole s posloupností řazení
	 */
	public function orderByControl($order) {
		if ($order && !is_array($order)) {
			Dbg::log("Error: Argument order must be an array");
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Shromáždí podmínky pro řazení posloupnosti z pole a vyrobí z něj řetězec pro SQL dotaz
	 * @param {array} order Pole s posloupností řazení
	 */
	public function makeSorting($order) {
		if ($order) {
			$sort = " order by ";
			$sortCount = count($order);
			for ($k=0; $k < $sortCount; $k++) {
				$sort .= $this->mres($order[$k]);
				if ($k != $sortCount-1) {
					$sort .= ", ";
				}
			}
			return $sort;
		} else {
			return false;
		}
	}

	/**
	 * Porovnávání pro dotazy v MySQL
	 * @param {string} grader Porovnávací operátor
	 */
	public function getComparsion($grader) {
		switch ($grader) {
			case "<":
			case ">":
			case "<=":
			case ">=":
			case "=":
			case "like":
				return $grader;
				break;
		}
		return false;
	}

	/**
	 * Vrátí pole se seznamem řádků z tabulky na základě daných kritérií pro SQL dotaz
	 * @param {array} cols Seznam sloupců (lze použít i tvary count(nazevSloupce) apod.) - povinné
	 * @param {string} table Název tabulky - povinné
	 * @param {array} where Podmínky, může být false - nepovinné (nebo vložená pole, kde první prvek v poli značí porovnávač a druhý hodnotu, se kterou porovnáváme)
	 * @param {array} order Řazení sloupců (dle posloupnosti), může být false - nepovinné
	 * @param {float} limit Limit počtu zobrazení
	 */
	public function getList($cols, $table, $where = false, $order = false, $limit = false) {
		if (!is_array($cols)) {
			Dbg::log("Error: Argument cols must be an array");
			return false;
		}

		if (!$this->orderByControl($order)) return false;

		# Řazení podle sloupců
		$sort = $this->makeSorting($order);

		# Sloupce, které budeme chtít načíst
		$colNames = "";
		$colsCount = count($cols);
		for ($j=0; $j < $colsCount; $j++) {
			$colNames .= $this->mres($cols[$j]);
			if ($j != $colsCount-1) {
				$colNames .= ", ";
			}
		}

		# Pole s podmínkama (where)
		$conCount = count($where);
		if ($conCount == 0 || !$where) {
			$conditions = "";
		} else {
			$conditions = " where ";
			$l = 0;
			foreach ($where as $key => $value) {
				if (is_array($value)) {
					if (is_array($value[0])) {
						$countVal = count($value);
						for ($m=0; $countVal > $m; $m++) {
							$grader = $this->getComparsion($value[$m][0]);
							$addVal = $this->mres($value[$m][1]);
							if ($grader) {
								$conditions .= $this->mres($key)." ". $grader ." '".$addVal."'";
								if ($m != $countVal-1) $conditions .= " and ";
							}
						}
					} else {
						$grader = $this->getComparsion($value[0]);
						$addVal = $this->mres($value[1]);
						if ($grader) $conditions .= $this->mres($key)." ". $grader ." '".$addVal."'";
					}
				} else {
					$conditions .= $this->mres($key)." like '".$this->mres($value)."'";
				}
				if ($l != $conCount-1) {
					$conditions .= " and ";
				}
				$l++;
			}
		}

		# Limit počtu zobrazení
		if ($limit) {
			$limit = sprintf(" limit %d", $limit);
		}

		$request = mysqli_query($this->session, $sql = sprintf("select %s from %s_%s%s%s%s",
			$colNames, Config::get("mysqlPrefix"), $table, $conditions, $sort, $limit)
		);

		if (!$request) {
			Dbg::log(sprintf("Error: Cannot select data from %s", $table));
			return false;
		}

		$data = array();
		$i = 0;
		while($arr = mysqli_fetch_assoc($request)) {
			foreach($arr as $key => $value) {
				$data[$i][$key] = $value;
			}
			$i++;
		}

		return $data;
	}

	/**
	 * Vyprázdní celou tabulku
	 * @param {string} table Název tabulky - povinné
	 */
	public function truncateTable($table) {
		if (!$table) return false;
		return mysqli_query($this->session, $sql = sprintf("truncate table %s_%s", Config::get("mysqlPrefix"), $table));
	}
}
?>
