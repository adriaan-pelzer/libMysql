<?php
require_once (dirname (__FILE__).'/config.mysql.php');

class Mysql {
    private $msql = NULL;
    private $affected_rows = -1;
    private $inserted_id = -1;

    public $error = NULL;

    function __construct () {
        if (!($this->msql = mysql_connect (DB_HOST, DB_USER, DB_PASS))) {
            $this->error = "Cannot connect to mysql database";
            return;
        }

        if (!(mysql_select_db (DB_BASE, $this->msql))) {
            $this->error = "Cannot select mysql database";
            return;
        }

        return;
    }

    private function build_values ($fields) {
        if ($fields == NULL) {
            return "*";
        } else {
            $field_string = "";
            foreach ($fields as $field=>$value) {
                if ($field_string == "") {
                    if ($value === NULL) {
                        $field_string = "NULL";
                    } else {
                        $field_string = "'".mysql_real_escape_string ($value)."'";
                    }
                } else {
                    if ($value === NULL) {
                        $field_string .= ",NULL";
                    } else {
                        $field_string .= ",'".mysql_real_escape_string ($value)."'";
                    }
                }
            }

            return $field_string;
        }
    }

    private function build_fields_from_values ($fields) {
        if ($fields == NULL) {
            return "*";
        } else {
            $field_string = "";
            foreach ($fields as $field) {
                if ($field_string == "") {
                    $field_string = $field;
                } else {
                    $field_string .= ",".$field;
                }
            }

            return $field_string;
        }
    }

    private function build_fields ($fields) {
        if ($fields == NULL) {
            return "*";
        } else {
            $field_string = "";
            foreach ($fields as $field=>$value) {
                if ($field_string == "") {
                    $field_string = "`".$field."`";
                } else {
                    $field_string .= ",`".$field."`";
                }
            }

            return $field_string;
        }
    }

    private function build_where ($where, $comparators=NULL) {
        if ($where == NULL) {
            return "1";
        } else {
            $field_string = "";

            foreach ($where as $field=>$value) {
                if (($comparators != NULL) && (isset ($comparators[$field]))) {
                    $comparator = $comparators[$field];
                } else {
                    $comparator = "=";
                }

                if ($field_string == "") {
                    if ($value === NULL) {
                        $field_string = "`".$field."`".$comparator."NULL";
                    } else {
                        $field_string = "`".$field."`".$comparator."'".mysql_real_escape_string($value)."'";
                    }
                } else {
                    if ($value === NULL) {
                        $field_string .= " AND `".$field."`".$comparator."NULL";
                    } else {
                        $field_string .= " AND `".$field."`".$comparator."'".mysql_real_escape_string($value)."'";
                    }
                }
            }

            return $field_string;
        }
    }

    private function build_update ($fields) {
        if ($fields == NULL) {
            return "1";
        } else {
            $update_string = "";

            foreach ($fields as $field=>$value) {
                if ($update_string == "") {
                    if ($value === NULL) {
                        $update_string = "`".$field."`=NULL";
                    } else {
                        $update_string = "`".$field."`='".mysql_real_escape_string($value)."'";
                    }
                } else {
                    if ($value === NULL) {
                        $update_string .= ",`".$field."`=NULL";
                    } else {
                        $update_string .= ",`".$field."`='".mysql_real_escape_string($value)."'";
                    }
                }
            }

            return $update_string;
        }
    }

    public function exec ($query) {
        if (!($result = mysql_query ($query))) {
            $this->error = "Cannot run query: ".$query.": ".mysql_error ($this->msql);
            return FALSE;
        }

        return $result;
    }

    private function _select ($table_name, $fields=NULL, $where=NULL, $comparators=NULL, $extra=NULL) {
        if (!($field_string = $this->build_fields_from_values ($fields))) {
            return FALSE;
        }

        if (!($where_string = $this->build_where ($where, $comparators))) {
            return FALSE;
        }

        if (!($result = mysql_query (($query = "SELECT ".$field_string." FROM `".$table_name."` WHERE ".$where_string." ".$extra), $this->msql))) {
            $this->error = "Cannot run query: ".$query.": ".mysql_error ($this->msql);
            return FALSE;
        }

        $this->inserted_id = -1;

        return $result;
    }

    public function select ($table_name, $fields=NULL, $where=NULL, $comparators=NULL, $extra=NULL) {
        if (!($result = $this->_select ($table_name, $fields, $where, $comparators, $extra))) {
            return FALSE;
        }

        $result_arr = array();

        while ($row = mysql_fetch_object ($result)) {
            array_push ($result_arr, $row);
        }

        return $result_arr;
    }

    public function insert ($table_name, $fields=NULL, $ignore=FALSE) {
        if (!($field_string = $this->build_fields ($fields))) {
            return FALSE;
        }

        if (!($value_string = $this->build_values ($fields))) {
            return FALSE;
        }

        if (($ignore === TRUE) || ($ignore === FALSE)) {
            if (!(mysql_query (($query = "INSERT ".($ignore?"IGNORE ":"")."INTO `".$table_name."` (".$field_string.") VALUES (".$value_string.")"), $this->msql))) {
                $this->error = "Cannot insert entry: ".$query.": ".mysql_error ($this->msql);
                return FALSE;
            }
        } else {
            if (!(mysql_query (($query = "INSERT INTO `".$table_name."` (".$field_string.") VALUES (".$value_string.") ON DUPLICATE KEY UPDATE ".$ignore), $this->msql))) {
                $this->error = "Cannot insert entry: ".$query.": ".mysql_error ($this->msql);
                return FALSE;
            }
        }

        $this->affected_rows = mysql_affected_rows ($this->msql);
        $this->inserted_id = mysql_insert_id ($this->msql);

        return TRUE;
    }

    public function update ($table_name, $fields=NULL, $where=NULL) {
        if (!($update_string = $this->build_update ($fields))) {
            return FALSE;
        }

        if (!($where_string = $this->build_where ($where))) {
            return FALSE;
        }

        if (!(mysql_query (($query = "UPDATE `".$table_name."` SET ".$update_string." WHERE ".$where_string), $this->msql))) {
            $this->error = "Cannot update entry/entries: ".$query.": ".mysql_error ($this->msql);
            return FALSE;
        }

        $this->affected_rows = mysql_affected_rows ($this->msql);
        $this->inserted_id = -1;

        return TRUE;
    }

    public function delete ($table_name, $fields=NULL, $where=NULL) {
        if (!($where_string = $this->build_where ($where))) {
            return FALSE;
        }

        if (!(mysql_query (($query = "DELETE FROM `".$table_name."` WHERE ".$where_string), $this->msql))) {
            $this->error = "Cannot delete entry/entries: ".$query.": ".mysql_error ($this->msql);
            return FALSE;
        }

        $this->affected_rows = mysql_affected_rows ($this->msql);
        $this->inserted_id = -1;

        return TRUE;
    }

    public function num_rows ($table_name, $fields=NULL, $where=NULL, $comparators=NULL) {
        if (!($result = $this->_select ($table_name, $fields, $where, $comparators))) {
            return FALSE;
        }

        return mysql_num_rows ($result);
    }

    public function affected_rows () {
        return $this->affected_rows;
    }

    public function inserted_id () {
        return $this->inserted_id;
    }
}
?>
