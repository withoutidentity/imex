<?php
$csv = __DIR__.'/../data/segments_out.csv';
if (!file_exists($csv)) {
  http_response_code(500);
  echo "Missing $csv. Run: php scripts/process_segments.php";
  exit;
}
$rows = array_map('str_getcsv', file($csv));
$hdr = array_shift($rows);
$data = array_map(fn($r)=>array_combine($hdr,$r), $rows);
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Preview Zones</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<style>html,body,#map{height:100%;margin:0}</style>
</head>
<body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([8.4305, 99.9630], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

const data = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE); ?>;

data.forEach(d => {
  const info = (extra='') => `<b>${d.corridor_name||''}</b><br>${d.segment_label||''}${extra}`;
  if (d.start_lat && d.start_lng) {
    L.marker([+d.start_lat, +d.start_lng]).addTo(map)
      .bindPopup(info(`<br><u>Start</u>: ${d.start_name||'-'}<br>ZIP: ${d.start_zip||'-'}`));
  }
  if (d.end_lat && d.end_lng) {
    L.marker([+d.end_lat, +d.end_lng]).addTo(map)
      .bindPopup(info(`<br><u>End</u>: ${d.end_name||'-'}<br>ZIP: ${d.end_zip||'-'}`));
  }
  if (d.geojson) {
    try {
      const gj = JSON.parse(d.geojson);
      L.geoJSON(gj).addTo(map).bindPopup(info(`<br><u>ZIP (centroid)</u>: ${d.zip_code||'-'}`));
    } catch(e){}
  }
});
</script>
</body>
</html> 