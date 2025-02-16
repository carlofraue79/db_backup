<?php
/*
    -- Specification--
    -- Class Name is: db_backup
    -- Methods
        -- connect(Server Name,User name,passwrd,Database)
            --Usages: If your project connected with database already
                    then you don't need to use this 'connect()' Method
            --Parameters
                1st: Server Name
                2nd: Username
                3rd: Password
                4th: Database Name
        -- tables() It will Return an array with all tables name in the database
        -- backup() It will Initialize The Backup
        -- download() It will download The Backup file in sql
            --Parameters
                1st: If you want to give a custom name of backup use it Default is 'backup'
        -- save() If you want to save the backup file into a server directory you can use it
            --parameters
                1st: Path URL
                2nd: file Name Default Name is backup_yyy-mm-dd
        --db_import(source)
            Usage: If you want to Import Database from SQL File Use this method
            --Parameters			1st: Source/Path of SQL file

*/


class db_backup
{
    private $exported_database;

    public function lineImport($conn, $fileName)
    {
        $lines = file($fileName);
        $result = false;
        foreach ($lines as $line) {
            $result = (mysqli_query($conn, $line) == true)? true : false;
        }
        return $result;
    }

    public function backup($conn)
    {
        /*-------------------------------------*/
        //------Creating Table SQL start-------//
        /*-------------------------------------*/

        $table_sql = array();
        foreach ($this->tables($conn) as $key => $table) {
            $tbl_query = mysqli_query($conn, "SHOW CREATE TABLE " . $table);
            $row2 = mysqli_fetch_row($tbl_query);
            $table_sql[] = $row2[1];
        }

        $solid_tablecreate_sql = implode("; \n\n", $table_sql);
        /*-------------------------------------*/
        //-------Creating Table SQL end--------//
        /*-------------------------------------*/


        /*-------------------------------------*/
        //------Inserting Data SQL Start-------//
        /*-------------------------------------*/
        $all_table_data = array();
        foreach ($this->tables($conn) as $key => $table) {
            $show_field = $this->view_fields($conn, $table);
            $solid_field_name = implode(", ", $show_field);
            $create_field_sql = "INSERT INTO `$table` ( " . $solid_field_name . ") VALUES \n";

            //Start checking data available
            mysqli_query($conn, "SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
            $table_data = mysqli_query($conn, "SELECT*FROM " . $table);
            if (!$table_data) {
                echo 'Could not run query: ' . mysqli_error($conn);
            }

            if (mysqli_num_rows($table_data) > 0) {
                $data_viewig = $this->view_data($conn, $table);
                $splice_data = array_chunk($data_viewig, 50);
                foreach ($splice_data as $each_datas) {
                    $solid_data_viewig = implode(", \n", $each_datas) . "; ";
                    $all_table_data[] = $create_field_sql . $solid_data_viewig;
                }
            } else {
                $all_table_data[] = null;
            }
            //End checking data available
        }
        $entiar_table_data = implode(" \n\n\n", $all_table_data);
        /*-------------------------------------*/
        //-------Inserting Data SQL End--------//
        /*-------------------------------------*/
        $this->exported_database = $solid_tablecreate_sql . "; \n \n" . $entiar_table_data;
        return $this;
    }

    /**
     * @param $conn The link of connection
     * @param $fileName the name of csv
     * @param the name of table is imported
     * @return int return number lines are imported into tables;
     */
    public function importFromCsv($conn, $fileName, $database_table)
    {
        $values_array = array();
        $input = fopen($fileName, 'a+');
        $input2 = fopen($fileName, 'a+');
        $first_row = fgetcsv($input, 1024, ';');
        foreach ($first_row as $name) {
            $values_array[] = ':' . trim($name);
        }
        $tmp_columns = implode(',', fgetcsv($input2, 1024, ';'));
        $replace="`,`";
        $columns = substr($tmp_columns, 0, strrpos($tmp_columns, ','));
        $tmp = '`'.preg_replace("/[',']/m", $replace, $columns).'`';
       
        $count = 0;
        while ($row = fgetcsv($input, 1024, ';')) {
            $sql = "INSERT INTO $database_table($tmp) VALUES (";
           
            // $query = $conn->prepare($sql);
            for ($i = 0; $i < count($row); $i++) {
                $sql.= "'".trim($row[$i])."',";
                // '`'.$row[$i].'`,';
                //$query->bindParam($values_array[$i], $row[$i]);
            }
            $tmp_sql=substr($sql, 0, strrpos($sql, ",''"));
            $tmp_sql.=')';
            echo $tmp_sql.PHP_EOL;
            mysqli_query($conn, $tmp_sql) or die(mysqli_error($conn));
            //$query->execute();
            $count++;
        }
        return $count;
    }
    //Additional Methods
    /*-------------------------------------*/
    //--------Functions Start here---------//
    /*-------------------------------------*/

    public function download($name = 'backup')
    {
        /*//Download
        $file_name="Tmpdata.sql";
        $file=fopen($file_name,"w+");
        fwrite($file, $this->exported_database);*/

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $name . '.sql');
        echo $this->exported_database;
        /*readfile($file_name);
        fclose($file);
        unlink($file_name);*/
    }

    public function save($path, $name = "")
    {
        $name = ($name != "")? $name : 'backup_' . date('Y-m-d');

        //Save file
        $file = fopen($path . $name . ".sql", "w+");
        $fw = fwrite($file, $this->exported_database);
        if (!$fw) {
            return false;
        } else {
            return true;
        }
    }

    public function connect($server, $user, $pass, $db)
    {
        $conn = mysqli_connect($server, $user, $pass, $db);

        if (!$conn) {
            echo mysqli_error($conn);
        }
        return $conn;
    }

    public function tables($conn)
    {
        /*-------------------------------------*/
        //------Creating Table List start------//
        /*-------------------------------------*/
        $tb_name = mysqli_query($conn, "SHOW TABLES");
        $tables = array();
        while ($tb = mysqli_fetch_row($tb_name)) {
            $tables[] = $tb[0];
        }
        /*-------------------------------------*/
        //-------Creating Table List end-------//
        /*-------------------------------------*/
        return $tables;
    }

    public function view_fields($conn, $tablename)
    {
        $all_fields = array();
        $fields = mysqli_query($conn, "SHOW COLUMNS FROM " . $tablename);
        if (!$fields) {
            echo 'Could not run query: ' . mysqli_error($conn);
        }

        if (mysqli_num_rows($fields) > 0) {
            while ($field = mysqli_fetch_assoc($fields)) {
                $all_fields[] = "`" . $field["Field"] . "`";
            }
        }
        return $all_fields;
    }

    public function getItem($conn, $sql, $table, $saveonFile=false)
    {
        $return ='';
        if ($result = mysqli_query($conn, $sql)) {
            $num_fields = mysqli_num_fields($result);
            if ($num_fields > 0) {
                echo 'Colonne sul server remoto esistenti '.'\n';
            }
            for ($i = 0; $i < $num_fields; $i++) {
                while ($row = mysqli_fetch_row($result)) {
                    $return.= 'INSERT INTO '.$table.' VALUES(';
                    for ($j=0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        //$row[$j] = preg_replace("\n","\\n",$row[$j]);
                        if (isset($row[$j])) {
                            $return.= '"'.$row[$j].'"' ;
                        } else {
                            $return.= '""';
                        }
                        if ($j < ($num_fields-1)) {
                            $return.= ',';
                        }
                    }
                    $return.= ");\n";
                }
            }
            $return.="\n\n\n";
        }
       
        $filename=$table.'.sql';
        if ($saveonFile) {
            $handle = fopen($filename, 'w');
            fwrite($handle, $return);
            fclose($handle);
        }
        
        return $return;
    }
    public function view_data($conn, $tablename)
    {
        $all_data = array();
        $table_data = mysqli_query($conn, "SELECT*FROM " . $tablename);
        if (!$table_data) {
            echo 'Could not run query: ' . mysqli_error($conn);
        }

        if (mysqli_num_rows($table_data) > 0) {
            while ($t_data = mysqli_fetch_row($table_data)) {
                $per_data = array();
                foreach ($t_data as $key => $tb_data) {
                    $per_data[] = "'" . str_replace("'", "\'", $tb_data) . "'";
                }
                $solid_data = "(" . implode(", ", $per_data) . ")";
                $all_data[] = $solid_data;
            }
        }
        return $all_data;
    }


    /*-------------------------------------*/
    //---------Functions End here----------//
    /*-------------------------------------*/

    //Export End here==================================================================
    //Import Start here==================================================================
    public function db_import($conn, $file_path)
    {
        $tbl_query = null;
        foreach ($this->tables($conn) as $key => $table) {
            $tbl_query = mysqli_query($conn, "DROP TABLE IF EXISTS " . $table);
        }

        //---------------------------------------------------------------------------
        //Forign code Start here
        //---------------------------------------------------------------------------
        $templine = '';
        // Read in entire file
        $lines = file($file_path);
        // Loop through each line
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }

            // Add this line to the current segment
            $templine .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                // Perform the query
                mysqli_query($conn, $templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error($conn) . '<br /><br />');
                // Reset temp variable to empty
                $templine = '';
            }
        }

        //echo "Database imported successfully <br/>";
        return true;
    }
}
