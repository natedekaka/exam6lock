<?php
require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

if (!isset($_GET['ujian']) || empty($_GET['ujian'])) {
    die("Parameter tidak valid");
}

$id_ujian = (int)$_GET['ujian'];

$stmt = $conn->prepare("SELECT judul_ujian FROM ujian WHERE id = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();
$ujian = $result->fetch_assoc();
$stmt->close();

if (!$ujian) {
    die("Ujian tidak ditemukan");
}

$sql = "SELECT h.*, 
                (SELECT COUNT(*) FROM exam_violations v WHERE v.id_ujian = h.id_ujian AND v.nis = h.nis) as jumlah_pelanggaran
                FROM hasil_ujian h 
                WHERE h.id_ujian = ? 
                ORDER BY h.total_skor DESC, h.nama ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();

$hasil_list = [];
while ($row = $result->fetch_assoc()) {
    $hasil_list[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total_soal FROM soal WHERE id_ujian = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result_soal = $stmt->get_result();
$total_soal = $result_soal->fetch_assoc()['total_soal'];
$stmt->close();

$nama_file = 'Rekap_Nilai_' . str_replace(' ', '_', $ujian['judul_ujian']) . '_' . date('Ymd_His');

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$nama_file.'.xls"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

echo '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">
  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
   <Author>'.htmlspecialchars($sekolah['nama_sekolah']).'</Author>
   <Title>'.htmlspecialchars($ujian['judul_ujian']).'</Title>
   <Subject>Rekap Nilai Ujian</Subject>
   <Created>'.date('Y-m-d\TH:i:s\Z').'</Created>
  </DocumentProperties>
  <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
   <WindowHeight>9000</WindowHeight>
   <WindowWidth>13860</WindowWidth>
   <WindowTopX>240</WindowTopX>
   <WindowTopY>45</WindowTopY>
  </ExcelWorkbook>
  <Styles>
   <Style ss:ID="Default" ss:Name="Normal">
    <Alignment ss:Vertical="Center"/>
    <Borders/>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11"/>
    <Interior/>
    <NumberFormat/>
    <Protection/>
   </Style>
   <Style ss:ID="Header">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#4472C4" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="Title">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="14" ss:Bold="1" ss:Color="#2C3E50"/>
   </Style>
   <Style ss:ID="Subtitle">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="10" ss:Color="#7F8C8D"/>
   </Style>
   <Style ss:ID="DataCenter">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
   </Style>
   <Style ss:ID="DataLeft">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
   </Style>
   <Style ss:ID="RowAlt">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="RowAltLeft">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="Rank1">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11" ss:Bold="1"/>
    <Interior ss:Color="#FFD700" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="Rank2">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11" ss:Bold="1"/>
    <Interior ss:Color="#C0C0C0" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="Rank3">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11" ss:Bold="1"/>
    <Interior ss:Color="#CD7F32" ss:Pattern="Solid"/>
   </Style>
   <Style ss:ID="SkorTinggi">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
     <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
     <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
    </Borders>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="11" ss:Bold="1" ss:Color="#27AE60"/>
   </Style>
   <Style ss:ID="Footer">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" x:CharSet="238" ss:Size="9" ss:Color="#7F8C8D"/>
   </Style>
  </Styles>
  <Worksheet ss:Name="Rekap Nilai">
   <Table ss:ExpandedColumnCount="8" ss:ExpandedRowCount="'.(count($hasil_list) + 10).'" x:FullColumns="1" x:FullRows="1" ss:DefaultColumnWidth="100" ss:DefaultRowHeight="20">
    <Column ss:Index="1" ss:Width="50"/>
    <Column ss:Index="2" ss:Width="120"/>
    <Column ss:Index="3" ss:Width="200"/>
    <Column ss:Index="4" ss:Width="100"/>
    <Column ss:Index="5" ss:Width="80"/>
    <Column ss:Index="6" ss:Width="80"/>
    <Column ss:Index="7" ss:Width="80"/>
    <Column ss:Index="8" ss:Width="150"/>
    
    <Row ss:Height="25">
     <Cell ss:MergeAcross="7" ss:StyleID="Title">
      <Data ss:Type="String">'.htmlspecialchars($ujian['judul_ujian']).'</Data>
     </Cell>
    </Row>
    <Row ss:Height="18">
     <Cell ss:MergeAcross="7" ss:StyleID="Subtitle">
      <Data ss:Type="String">Total Soal: '.$total_soal.' | Tanggal Export: '.date('d F Y, H:i:s').'</Data>
     </Cell>
    </Row>
    <Row ss:Height="5">
     <Cell ss:MergeAcross="7"><Data ss:Type="String"></Data></Cell>
    </Row>
    
    <Row ss:Height="25">
     <Cell ss:StyleID="Header"><Data ss:Type="String">No</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">NIS</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Nama Siswa</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Kelas</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Skor Asli</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Skor Akhir</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Potongan</Data></Cell>
     <Cell ss:StyleID="Header"><Data ss:Type="String">Waktu Submit</Data></Cell>
    </Row>';

$no = 1;
foreach ($hasil_list as $hasil) {
    $persentase = $total_soal > 0 ? ($hasil['total_skor'] / $total_soal) * 100 : 0;
    
    if ($hasil['skor_awal'] !== null) {
        $skor_asli = $hasil['skor_awal'];
        $penalty = $skor_asli - $hasil['total_skor'];
    } else {
        $penalty = min($hasil['jumlah_pelanggaran'] * 10, $hasil['total_skor'] * 0.5);
        $skor_asli = $hasil['total_skor'] + $penalty;
    }
    
    if ($no == 1 && $persentase == 100) {
        $row_style = 'Rank1';
    } elseif ($no == 2 && $persentase == 100) {
        $row_style = 'Rank2';
    } elseif ($no == 3 && $persentase == 100) {
        $row_style = 'Rank3';
    } elseif ($no % 2 == 0) {
        $row_style = $persentase >= 80 ? 'SkorTinggi' : 'RowAlt';
    } else {
        $row_style = $persentase >= 80 ? 'SkorTinggi' : 'DataCenter';
    }
    
    $nama_style = ($no % 2 == 0) ? 'RowAltLeft' : 'DataLeft';
    
    $waktu = !empty($hasil['waktu_submit']) ? date('d/m/Y H:i', strtotime($hasil['waktu_submit'])) : '-';
    
    echo '
    <Row ss:Height="20">
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="Number">'.$no.'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="String">'.htmlspecialchars($hasil['nis']).'</Data></Cell>
     <Cell ss:StyleID="'.$nama_style.'"><Data ss:Type="String">'.htmlspecialchars($hasil['nama']).'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="String">'.htmlspecialchars($hasil['kelas']).'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="Number">'.$skor_asli.'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="Number">'.$hasil['total_skor'].'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="Number">'.$penalty.'</Data></Cell>
     <Cell ss:StyleID="'.$row_style.'"><Data ss:Type="String">'.$waktu.'</Data></Cell>
    </Row>';
    $no++;
}

if (count($hasil_list) === 0) {
    echo '
    <Row ss:Height="30">
     <Cell ss:MergeAcross="7" ss:StyleID="DataCenter">
      <Data ss:Type="String">Belum ada peserta yang menyelesaikan ujian</Data>
     </Cell>
    </Row>';
}

echo '
    <Row ss:Height="15">
     <Cell ss:MergeAcross="7" ss:StyleID="Footer">
      <Data ss:Type="String">Dicetak pada: '.date('d F Y H:i:s').'</Data>
     </Cell>
    </Row>
   </Table>
   <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
    <PageSetup>
     <Layout ss:Orientation="Portrait"/>
     <PageMargins ss:Bottom="0.75" ss:Left="0.75" ss:Right="0.75" ss:Top="1" ss:Header="0.5" ss:Footer="0.5"/>
    </PageSetup>
    <Print>
     <ValidPrinterInfo/>
     <HorizontalCentered/>
     <VerticalCentered/>
    </Print>
    <Selected/>
    <FreezePanes/>
    <FrozenNoSplit/>
    <SplitHorizontal>4</SplitHorizontal>
    <TopRowBottomPane>4</TopRowBottomPane>
    <ActivePane>BottomPane</ActivePane>
   </WorksheetOptions>
  </Worksheet>
</Workbook>';
