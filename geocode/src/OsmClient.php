<?php
class OsmClient {
  private string $ua;
  private string $cacheDir;

  public function __construct(
    string $ua = 'VJB-Payroll-Geocoder/1.0 (contact: it@example.com)',
    string $cacheDir = __DIR__.'/../cache'
  ){
    $this->ua = $ua;
    $this->cacheDir = $cacheDir;
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
  }

  private function cacheKey(string $s): string { return sha1($s); }

  private function httpGetJson(string $url): array {
    $key = $this->cacheKey($url);
    $cacheFile = $this->cacheDir.'/'.$key.'.json';
    if (file_exists($cacheFile)) {
      return json_decode(file_get_contents($cacheFile), true) ?? [];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['User-Agent: '.$this->ua],
      CURLOPT_TIMEOUT => 25
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('HTTP error: '.curl_error($ch));
    curl_close($ch);
    usleep(1100000); // ≥ 1 req/sec (Nominatim policy)
    file_put_contents($cacheFile, $res);
    return json_decode($res, true) ?? [];
  }

  /** หา viewbox ของ Amphoe Mueang Nakhon Si Thammarat (ใช้จำกัดพื้นที่ค้นหา) */
  public function getViewboxAmphoe(): array {
    $q = urlencode('Amphoe Mueang Nakhon Si Thammarat, Thailand');
    $url = "https://nominatim.openstreetmap.org/search?format=json&polygon_geojson=0&limit=1&q={$q}";
    $data = $this->httpGetJson($url);
    if (!$data) throw new Exception('Viewbox not found');
    // boundingbox: [south, north, west, east]
    $bb = $data[0]['boundingbox'];
    return [$bb[2], $bb[3], $bb[0], $bb[1]]; // west, east, south, north
  }

  /** Forward geocode (point) จำกัดใน viewbox */
  public function findPoint(string $name, array $viewbox): ?array {
    if (!$name || strtolower($name) === 'null') return null;
    [$w,$e,$s,$n] = $viewbox;
    $q = urlencode($name.' Nakhon Si Thammarat');
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q={$q}&viewbox=$w,$n,$e,$s&bounded=1";
    $res = $this->httpGetJson($url);
    if (!$res) return null;
    return ['lat' => (float)$res[0]['lat'], 'lng' => (float)$res[0]['lon']];
  }

  /** ดึงเส้นถนนจาก Overpass เป็น LineString (เลือก way แรกพอใช้งาน) */
  public function getRoadLine(string $roadName, string $district='Mueang Nakhon Si Thammarat'): ?array {
    if (!$roadName || strtolower($roadName) === 'null') return null;
    $q = <<<QL
[out:json][timeout:25];
area["name"="$district"]["boundary"="administrative"]->.a;
( way(area.a)[highway]["name"="$roadName"];
  way(area.a)[highway]["name:th"="$roadName"]; );
(._;>;);
out geom;
QL;
    $url = "https://overpass-api.de/api/interpreter?data=".urlencode($q);
    $data = $this->httpGetJson($url);
    if (!isset($data['elements'])) return null;
    foreach ($data['elements'] as $el) {
      if ($el['type']==='way' && !empty($el['geometry'])) {
        $coords = array_map(fn($g)=>[$g['lon'],$g['lat']], $el['geometry']);
        return ['type'=>'LineString','coordinates'=>$coords];
      }
    }
    return null;
  }

  /** Reverse geocoding → postcode */
  public function reversePostcode(float $lat, float $lng): ?string {
    $lat = round($lat, 5); $lng = round($lng, 5);
    $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lng}&addressdetails=1";
    $data = $this->httpGetJson($url);
    if (!$data) return null;
    if (!empty($data['address']['postcode'])) return (string)$data['address']['postcode'];
    if (!empty($data['postcode'])) return (string)$data['postcode'];
    return null;
  }
} 