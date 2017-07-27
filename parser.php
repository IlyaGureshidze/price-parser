<?php
error_reporting(E_ALL); 
ini_set("display_errors", 1); 

if(!empty($_SERVER)) {
  header('Content-Type: text/html; charset=utf-8');
}
require_once '../config.core.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
$modx->initialize('web');

//получим из почты файл
//file_get_contents(__DIR__ ."/mail.php");
/* 
* Считывает данные из любого excel файла и созадет из них массив.
* $filename (строка) путь к файлу от корня сервера
*/
function parse_excel_file( $filename ){
// подключаем библиотеку
require_once dirname(__FILE__) . '/include/PHPExcel.php';

$result = array();

// получаем тип файла (xls, xlsx), чтобы правильно его обработать
$file_type = PHPExcel_IOFactory::identify( $filename );
// создаем объект для чтения
$objReader = PHPExcel_IOFactory::createReader( $file_type );
$objPHPExcel = $objReader->load( $filename ); // загружаем данные файла в объект
$result = $objPHPExcel->getActiveSheet(); // выгружаем данные из объекта в массив

return $result;
}

$inputFile = __DIR__ . "/price4.xls";
$dir = __DIR__ . "/dir";

echo '<pre>';
$currentLevels = array();
$sheet = parse_excel_file($inputFile);

$resource_ids = array();
foreach ($sheet->getRowIterator() as $i => $row) {
$data = array();
foreach ($row->getCellIterator() as $key => $cell) {
$data[] = $cell->getCalculatedValue();
}
$isCat = !empty($data[0]) && empty(implode('', array_slice($data, 1)));
$catName = trim($data[0]);
$price = $data[3];
$level = $sheet->getRowDimension($i)->getOutlineLevel();
if ($isCat) $currentLevels[$level] = $catName;

if ($i == 1) continue; // шапка
//if ($catName == "") continue;//Пустые поля

$resource_ids[] = $catName;
//Проверяем,есть ли такой ресурс на сайте через значение tv поля resource_id
$tvr = $modx->getCollection('modTemplateVarResource', array(
  'tmplvarid' => 23,
  'value' => $catName
));
//если есть tv - есть и ресурс на сайте
if (count($tvr)) {
  foreach($tvr as $tv){
    if($Res = $modx->getObject('modResource',$tv->get('contentid'))){
      if(!$isCat){
        if($Res->getTVValue('price') != $price){
          $Res->setTVValue('price',$price);
          $Res->save();
        }
      }
      /* if($Res->get('published') != 1){
      $Res->set('published',1);
      $Res->save();
      }*/
    }
  }
}
//если нет - получаем родителя и создаем новый ресурс
else {
  $parent = $currentLevels[$level-1] ? $currentLevels[$level-1] : 'корень';
  if($parent == 'корень'){
    if($catName){
      $publishedon = date('Y-m-d H:i:s');
      $newRes1 = $modx->newObject('modResource');
      $newRes1->set('parent',89);
      $newRes1->set('pagetitle',$catName);
      $newRes1->set('template',$isCat ? 2 : 3);
      $newRes1->set('published',1);
      $newRes1->set('publishedon',$publishedon);
      $newRes1->set('cacheable',0);
      $newRes1->set('context_key','shop');
      $newRes1->save();
      $newRes1->set('alias',$newRes1->get('id'));
      $newRes1->setTVValue('from_price',1);
      $newRes1->setTVValue('resource_id',$catName);
      $newRes1->save();
    }
  }
else{
    $tvParent = $modx->getObject('modTemplateVarResource', array(
    'tmplvarid' => 23,
    'value' => $parent
    ));
    if($tvParent){
      if($catName){
          $publishedon2 = date('Y-m-d H:i:s');
          $newRes2 = $modx->newObject('modResource');
          $newRes2->set('parent',$tvParent->get('contentid'));
          $newRes2->set('pagetitle',$catName);
          $newRes2->set('template',$isCat ? 2 : 3);
          $newRes2->set('published',1);
          $newRes2->set('publishedon',$publishedon2);
          $newRes2->set('cacheable',0);
          $newRes2->set('context_key','shop');
          $newRes2->save();
          $newRes2->set('alias',$newRes2->get('id'));
          $newRes2->setTVValue('from_price',1);
          $newRes2->setTVValue('resource_id',$catName);

          if(!$isCat){
            $newRes2->setTVValue('price',$price); 
          }
          $newRes2->save();
      }
      else continue;
   }
  }
}

}
$siteResources = $modx->getCollection('modResource', array(
"template:IN" => array(2,3),
'published' => 1,
'deleted' => 0));
foreach($siteResources as $siteRes){
  if($siteRes->getTVValue('from_price') == 1){
    if(!in_array($siteRes->getTVValue('resource_id'),$resource_ids)){
      //print_r($siteRes->get('pagetitle').' is unpublished<br>');
      $siteRes->set('published',0);
      $siteRes->save();
    }
  }
}
