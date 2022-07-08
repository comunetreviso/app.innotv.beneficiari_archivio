<?php

require_once(ROOT_PATH . "/lib/PHPExcel/PHPExcel.php");

class beneficiario {
    private $conn;
    private $tipologia;
    private $beneficiario;
    private $anno;
    private $settore;
    private $fileInfo;
    
    function __construct($conn, $tipologia = null, $beneficiario = null, $anno = null, $settore = null, $fileInfo = null) {
        $this->conn = $conn;
        $this->tipologia = $tipologia;
        $this->beneficiario = $beneficiario;
        $this->anno = $anno;
        $this->settore = $settore;
        $this->fileInfo = $fileInfo;
    }
    
    function get_tipologie() {
        $results = array();       
        $stmt = $this->conn->query("SELECT DISTINCT tipologia FROM beneficiari ORDER BY tipologia");

        while ($row = $stmt->fetch()) {
            array_push($results, $row["tipologia"]);
        }

        return $results;
    }
    
    function get_settori() {
        $results = array();     
        $stmt = $this->conn->query("SELECT DISTINCT settore FROM beneficiari ORDER BY settore");

        while ($row = $stmt->fetch()) {
            array_push($results, $row["settore"]);
        }

        return $results;
    }
    
    function get_anni_riferimento() {
        $results = array();       
        $stmt = $this->conn->query("SELECT DISTINCT anno FROM beneficiari ORDER BY anno DESC");

        while ($row = $stmt->fetch()) {
            array_push($results, $row["anno"]);
        }

        return $results;
    }
    
    function ricerca() {
        $results = array();
        $params = array();
        $qry = "SELECT {select} FROM beneficiari";

        if (!empty($this->tipologia) || !empty($this->beneficiario) || !empty($this->anno) || !empty($this->settore)) {
            $qry .= " WHERE ";

            if (!empty($this->anno)) {
                $qry .= "anno = :anno";
                $params[":anno"] = $this->anno;
            }

            if (!empty($this->tipologia)) {
                $qry .= " AND tipologia = :tipologia";
                $params[":tipologia"] = $this->tipologia;
            }

            if (!empty($this->beneficiario)) {
                $qry .= " AND beneficiario LIKE :beneficiario";
                $params[":beneficiario"] = "%" . $this->beneficiario . "%";
            }

            if (!empty($this->settore)) {
                $qry .= " AND settore = :settore";
                $params[":settore"] = $this->settore;
            }
        }

        // calcolo importo totale

        if (!$stmt_1 = $this->conn->prepare(str_replace("{select}", "SUM(IFNULL(importo, 0))", $qry))) {
            throw new Exception("Errore preparazione statement 1.");
        }

        if (!$stmt_1->execute($params)) {
            throw new Exception("Errore esecuzione statement 1.");
        }

        $totImporto = $stmt_1->fetchColumn();

        // elenco risultati

        $qry = str_replace("{select}", "id, tipologia, beneficiario, importo, norma_titolo_attr, settore, atto, progetto_finalita", $qry);
        $qry .= " ORDER BY tipologia, beneficiario";

        if (!$stmt_2 = $this->conn->prepare($qry)) {
            throw new Exception("Errore preparazione statement 2.");
        }

        if (!$stmt_2->execute($params)) {
            throw new Exception("Errore esecuzione statement 2.");
        }

        while ($row = $stmt_2->fetch()) {
            $results[] = array(
                "id" => $row["id"],
                "tipologia" => $row["tipologia"],
                "beneficiario" => $row["beneficiario"],
                "importo" => $row["importo"],
                "norma_titolo_attr" => $row["norma_titolo_attr"],
                "settore" => $row["settore"],
                "atto" => $row["atto"],
                "progetto_finalita" => $row["progetto_finalita"]
            );
        }

        return array("tot_importo" => $totImporto, "risultati" => $results);					
    }
    
    function report_settore() {
        $results = array();
        
        if (!$stmt = $this->conn->prepare("SELECT settore, SUM(IFNULL(importo, 0)) AS importo_annuale FROM beneficiari WHERE anno = ? GROUP BY settore")) {
            throw new Exception("Errore preparazione statement.");
        }

        if (!$stmt->execute(array($this->anno))) {
            throw new Exception("Errore esecuzione statement.");
        }

        while ($row = $stmt->fetch()) {
            $results[] = array(
                "settore" => $row["settore"],
                "importo_annuale" => $row["importo_annuale"]
            );
        }

        return $results;					
    }
    
    function report_settore_anno() {
        $results = array();
        
        if (!$stmt = $this->conn->prepare("SELECT anno, SUM(IFNULL(importo, 0)) AS importo_annuale FROM beneficiari WHERE settore = ? GROUP BY anno ORDER BY anno")) {
            throw new Exception("Errore preparazione statement.");
        }

        if (!$stmt->execute(array($this->settore))) {
            throw new Exception("Errore esecuzione statement.");
        }

        while ($row = $stmt->fetch()) {
            $results[] = array(
                "anno" => $row["anno"],
                "importo_annuale" => $row["importo_annuale"]
            );
        }

        return $results;					
    }
    
    function report_tipologia() {
        $results = array();
        
        if (!$stmt = $this->conn->prepare("SELECT tipologia, SUM(IFNULL(importo, 0)) AS importo_annuale FROM beneficiari WHERE anno = ? GROUP BY tipologia")) {
            throw new Exception("Errore preparazione statement.");
        }

        if (!$stmt->execute(array($this->anno))) {
            throw new Exception("Errore esecuzione statement.");
        }

        while ($row = $stmt->fetch()) {
            $results[] = array(
                "tipologia" => $row["tipologia"],
                "importo_annuale" => $row["importo_annuale"]
            );
        }

        return $results;					
    }
    
    function report_tipologia_anno() {
        $results = array();
        
        if (!$stmt = $this->conn->prepare("SELECT anno, SUM(IFNULL(importo, 0)) AS importo_annuale FROM beneficiari WHERE tipologia = ? GROUP BY anno ORDER BY anno")) {
            throw new Exception("Errore preparazione statement.");
        }

        if (!$stmt->execute(array($this->tipologia))) {
            throw new Exception("Errore esecuzione statement.");
        }

        while ($row = $stmt->fetch()) {
            $results[] = array(
                "anno" => $row["anno"],
                "importo_annuale" => $row["importo_annuale"]
            );
        }

        return $results;					
    }
    
    function importa() {
        $filePath = realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "temp" . DIRECTORY_SEPARATOR . basename($this->fileInfo["name"]);
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        
        if ($ext != "xlsx") {
            throw new Exception("Sono ammessi solo file Excel con estensione XLSX.");
        }
                
        if (!move_uploaded_file($this->fileInfo["tmp_name"], $filePath)) {
            throw new Exception("Errore durante il caricamento del file.");
        }
        
        // lettura file Excel
        
        $inputFileType = PHPExcel_IOFactory::identify($filePath);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($filePath);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $columnCount = PHPExcel_Cell::columnIndexFromString($highestColumn);
        $titles = $sheet->rangeToArray("A1:" . $highestColumn . "1");
        
        // verifico che il modello per l'importazione sia rispettato
        
        $importColumns = array("Tipologia", "Beneficiario", "Importo", "Norma o titolo attribuzione", "Settore", "Atto", "Progetto/Finalità");
        
        if (count(array_diff($importColumns, $titles[0])) > 0) {
            throw new Exception("Il file caricato non contiene tutti i campi richiesti per l'importazione.");
        }
                        
        $body = $sheet->rangeToArray("A2:" . $highestColumn . $highestRow);     
        $data = array();
        
        for ($row = 0; $row <= $highestRow - 2; $row++) {
            $temp = array();
            
            for ($column = 0; $column <= $columnCount - 1; $column++) {
                $columnName = $titles[0][$column];
                
                if (in_array($columnName, $importColumns)) {
                    if ($columnName == "Importo") {
                        $temp[$columnName] = substr_replace(trim(str_replace(array("€", ".", ","), array("", "", ""), $body[$row][$column])), ".", -2, 0);
                    }

                    else {
                        $temp[$columnName] = $body[$row][$column];
                    }                 
                }
            }
            
            $data[$row] = $temp;
        }
        
        // creazione file CSV
        
        $csvPath = $filePath . ".csv";
        $fo = fopen($csvPath, "w+");
        
        foreach ($data as $d) {
            $d["anno"] = $this->anno;
            fputcsv($fo, $d);
        }
        
        fclose($fo);
                        
        // importazione dati nel database
                
        try {
            $this->conn->beginTransaction();
            
            if (!$stmt_1 = $this->conn->prepare("DELETE FROM beneficiari WHERE anno = ?")) {
                throw new Exception("Errore preparazione statement 1.");
            }
            
            if (!$stmt_1->execute(array($this->anno))) {
                throw new Exception("Errore esecuzione statement 1.");
            }
                        
            // importazione nella tabella dei beneficiari
            
            $qry = "LOAD DATA LOCAL INFILE '" . str_replace("\\", "/", $csvPath) . "' "
                   . "INTO TABLE beneficiari "
                   . "FIELDS TERMINATED BY ',' "
                   . "OPTIONALLY ENCLOSED BY '\"' "
                   . "LINES TERMINATED BY '\n' "
                   . "(tipologia, beneficiario, importo, norma_titolo_attr, settore, atto, progetto_finalita, anno)";
            
            if (!$stmt_2 = $this->conn->prepare($qry)) {
                throw new Exception("Errore preparazione statement 2.");
            }
            
            if (!$stmt_2->execute()) {
                throw new Exception("Errore esecuzione statement 2.");
            }
 
            $this->conn->commit();
            return $filePath;
        }   
        
        catch (Exception $e) {
            if ($this->conn != null) {
                $this->conn->rollBack();
            }
            
            throw $e;
        }
    }
}