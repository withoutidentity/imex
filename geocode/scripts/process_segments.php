#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__.'/../src/OsmClient.php';
$manual = require __DIR__.'/../src/names_map.php';

$in  = __DIR__.'/../data/segments_in.csv';
$out = __DIR__.'/../data/segments_out.csv';

$osm = new OsmClient();
$viewbox = $osm->getViewboxAmphoe();

/** fallback zip คร่าว ๆ สำหรับ อ.เมือง นครศรีธรรมราช */
$zipFallback = [
  '80000' => ['ในเมือง','คลัง','ท่าซัก','ท่าวัง','ท่าไร่','นาเคียน','ปากนคร','ปากพูน','มะม่วงสองต้น','โพธิ์เสด็จ','ไชยมนตรี'],
  '80280' => ['กำแพงเซา','ท่างิ้ว','นาทราย'],
  '80290' => ['ท่าเรือ'], // บางหมู่เท่านั้น ใช้เป็น fallback
  '80330' => ['บางจาก'],
];
function fallbackZipFromName(string $name, array $map): ?string {
  foreach ($map as $zip => $tambons) {
    foreach ($tambons as $t) {
      if ($t && mb_stripos($name, $t) !== false) return $zip;
    }
  }
  return null;
}
function centroidLine(array $coordinates): ?array {
  if (empty($coordinates)) return null;
  $sumLng=0; $sumLat=0; $n=0;
  foreach ($coordinates as $c) {
    if (!is_array($c) || count($c)<2) continue;
    $sumLng += (float)$c[0]; $sumLat += (float)$c[1]; $n++;
  }
  if ($n===0) return null;
  return ['lng'=>$sumLng/$n, 'lat'=>$sumLat/$n];
}

/** optional: เติม start/end อัตโนมัติจาก segment_label
 *  ตัวอย่าง pattern: "A - B", "A ถึง B"
 */
function inferEndpoints(string $label): array {
  $label = trim($label);
  $patterns = [
    '/(.+?)\s*-\s*(.+)/u',
    '/(.+?)\s*ถึง\s*(.+)/u'
  ];
  foreach ($patterns as $p) {
    if (preg_match($p, $label, $m)) {
      return [trim($m[1]), trim($m[2])];
    }
  }
  return ['', ''];
}

$fi = fopen($in, 'r');
if (!$fi) { fwrite(STDERR, "Cannot open $in\n"); exit(1); }
$fo = fopen($out, 'w');
fputcsv($fo, [
  'corridor_name','segment_label','geom_type',
  'start_name','start_lat','start_lng','start_zip',
  'end_name','end_lat','end_lng','end_zip',
  'zip_code','geojson'
]);

$header = fgetcsv($fi); // skip header
while (($row = fgetcsv($fi)) !== false) {
  // expected: corridor_name,segment_label,start_name,end_name,geom_type
  [$corridor,$label,$start,$end,$gtype] = array_pad($row, 5, '');

  // เติม start/end ถ้ายังว่าง
  if (!$start && !$end) {
    [$iStart, $iEnd] = inferEndpoints($label);
    if ($iStart) $start = $iStart;
    if ($iEnd)   $end   = $iEnd;
  }

  // หา point เริ่ม/จบ (manual override มาก่อน)
  $startPt = $manual[$start] ?? ($start ? $osm->findPoint(trim($start), $viewbox) : null);
  $endPt   = $manual[$end]   ?? ($end   ? $osm->findPoint(trim($end),   $viewbox) : null);

  // ZIP เริ่ม/จบ
  $startZip = null;
  if (!empty($startPt['lat']) && !empty($startPt['lng'])) {
    $startZip = $osm->reversePostcode($startPt['lat'], $startPt['lng']);
  }
  if (!$startZip && $start) $startZip = fallbackZipFromName($start, $zipFallback);

  $endZip = null;
  if (!empty($endPt['lat']) && !empty($endPt['lng'])) {
    $endZip = $osm->reversePostcode($endPt['lat'], $endPt['lng']);
  }
  if (!$endZip && $end) $endZip = fallbackZipFromName($end, $zipFallback);

  // line: ดึงเส้น แล้วคำนวณ ZIP จาก centroid
  $geojson = '';
  $mainZip = null;
  if (strtolower($gtype)==='line') {
    $line = $osm->getRoadLine(trim($start ?: $label));
    if ($line) {
      $geojson = json_encode($line, JSON_UNESCAPED_UNICODE);
      $c = centroidLine($line['coordinates'] ?? []);
      if ($c) $mainZip = $osm->reversePostcode($c['lat'], $c['lng']) ?? $mainZip;
    }
  }

  if (!$mainZip) {
    if ($startZip && $startZip === $endZip) $mainZip = $startZip;
    else $mainZip = $startZip ?? $endZip;
  }

  fputcsv($fo, [
    $corridor, $label, $gtype,
    $start, $startPt['lat'] ?? null, $startPt['lng'] ?? null, $startZip,
    $end,   $endPt['lat']   ?? null, $endPt['lng']   ?? null, $endZip,
    $mainZip, $geojson
  ]);
}
fclose($fi); fclose($fo);
echo "DONE -> data/segments_out.csv\n"; 