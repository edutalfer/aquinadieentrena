<?php
/* Genera tracks orientativos de la Cape Epic 2027 a partir de puntos de paso leídos en los mapas oficiales.
   Ruta por caminos reales con BRouter (perfil MTB). NO son los GPX oficiales. */
$out = getenv('HOME') . '/domains/aquinadieentrena.cc/public_html/capeepic/tracks';
@mkdir($out, 0755, true);

/* [lon, lat] en el orden del perfil oficial */
$S = [
 'P' => ['km'=>27,  'pts'=>[[18.8866,-34.0679],[18.9050,-34.0500],[18.9330,-34.0450],[18.9400,-34.0640],[18.9150,-34.0760],[18.8866,-34.0679]]],
 '1' => ['km'=>127, 'pts'=>[[19.3104,-33.3689],[19.4000,-33.3400],[19.5300,-33.3100],[19.5600,-33.2900],[19.4700,-33.2100],[19.4200,-33.2000],[19.3708,-33.1449],[19.3239,-33.2244],[19.3278,-33.2917],[19.3104,-33.3689]]],
 '2' => ['km'=>103, 'pts'=>[[19.3104,-33.3689],[19.3700,-33.3900],[19.4200,-33.4100],[19.4450,-33.3950],[19.4200,-33.4100],[19.4000,-33.4300],[19.3900,-33.4400],[19.3800,-33.4500],[19.4200,-33.4100],[19.4339,-33.4193],[19.3400,-33.3800],[19.3104,-33.3689]]],
 '3' => ['km'=>87,  'pts'=>[[19.3104,-33.3689],[19.3278,-33.2917],[19.3239,-33.2244],[19.3300,-33.2150],[19.3000,-33.2100],[19.2500,-33.1900],[19.2200,-33.2000],[19.1700,-33.2500],[19.1552,-33.2746],[19.1383,-33.2551]]],
 '4' => ['km'=>96,  'pts'=>[[19.1383,-33.2551],[19.1200,-33.2100],[19.1350,-33.1900],[19.1500,-33.1800],[19.1352,-33.2284],[19.1700,-33.2100],[19.1700,-33.2600],[19.1900,-33.2750],[19.1900,-33.2900],[19.1950,-33.3100],[19.1385,-33.2853],[19.1383,-33.2551]]],
 '5' => ['km'=>114, 'pts'=>[[19.1383,-33.2551],[19.0409,-33.3063],[19.0200,-33.3200],[19.0000,-33.3300],[18.8955,-33.3850],[18.9000,-33.4000],[18.9452,-33.4537],[18.9800,-33.5700],[19.0200,-33.6000],[19.0040,-33.6120]]],
 '6' => ['km'=>83,  'pts'=>[[19.0040,-33.6120],[19.0600,-33.6600],[19.0500,-33.6400],[19.0600,-33.6300],[19.0700,-33.6400],[19.0800,-33.6450],[19.0709,-33.6415],[19.0600,-33.6500],[19.0400,-33.6600],[19.0040,-33.6120]]],
 '7' => ['km'=>64,  'pts'=>[[19.0040,-33.6120],[19.0600,-33.6600],[19.0450,-33.6500],[19.0833,-33.6261],[19.0700,-33.6400],[19.0800,-33.6350],[19.0199,-33.6416],[19.0040,-33.6120]]],
];

function dist($a,$b){ $R=6371; $r=M_PI/180; $dLat=($b[1]-$a[1])*$r; $dLon=($b[0]-$a[0])*$r;
  $h=sin($dLat/2)**2+cos($a[1]*$r)*cos($b[1]*$r)*sin($dLon/2)**2; return 2*$R*asin(sqrt($h)); }

function brouter($a,$b){
  for($try=0;$try<3;$try++){
    $u="https://brouter.de/brouter?lonlats={$a[0]},{$a[1]}%7C{$b[0]},{$b[1]}&profile=mtb&alternativeidx=0&format=geojson";
    $j=json_decode(@file_get_contents($u,false,stream_context_create(["http"=>["timeout"=>40,"header"=>"User-Agent: ANE-capeepic/1.0\r\n"]])),true);
    if($j) return $j["features"][0]["geometry"]["coordinates"];
    sleep(3);
  }
  return null;
}
$only = array_slice($argv,1);
foreach ($S as $id => $st) {
  if ($only && !in_array($id,$only)) continue;
  $c=[]; $rectas=0;
  for($i=0;$i<count($st["pts"])-1;$i++){
    $a=$st["pts"][$i]; $b=$st["pts"][$i+1]; $seg=brouter($a,$b); $len=0;
    if($seg){ for($k=1;$k<count($seg);$k++) $len+=dist($seg[$k-1],$seg[$k]); }
    if(!$seg || $len>3*dist($a,$b)+3){ $seg=[$a,$b]; $rectas++; }
    if($c) array_shift($seg);
    $c=array_merge($c,$seg); usleep(800000);
  }
  $pts=[]; $last=null; $acc=0; $tot=0;
  foreach ($c as $i=>$p) {
    if ($last) { $d=dist($last,$p); $acc+=$d; $tot+=$d; }
    if (!$last || $acc>=0.06 || $i==count($c)-1) { $pts[]=[round($p[1],5),round($p[0],5),isset($p[2])?round($p[2]):null]; $acc=0; }
    $last=$p;
  }
  $data=["etapa"=>$id,"oficial_km"=>$st["km"],"ruta_km"=>round($tot,1),"tramos_rectos"=>$rectas,"puntos"=>$pts,"aviso"=>"Trazado orientativo reconstruido a partir de los mapas oficiales. No es el GPX oficial."];
  file_put_contents("$out/$id.json", json_encode($data));
  printf("%s: oficial %d km · ruta %.1f km · %d tramos rectos · %d puntos\n", $id, $st["km"], $tot, $rectas, count($pts));
}