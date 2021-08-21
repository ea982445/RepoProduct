<?
$start = microtime(true);
opcache_reset();
echo time();
$_SERVER['DOCUMENT_ROOT'] = '';
require_once($_SERVER['DOCUMENT_ROOT'] . ");
\Bitrix\Main\Loader::IncludeModule("l");
global $DB;
CModule::IncludeModule('iblock');
// echo 'ok';
// exit();

// file_get_contents("https://api.telegram.org/bot400991748:AAE0X0kN5LEdsPPHlCBa3shO96-ipf02JYA". '/sendMessage?chat_id=190539045&text=Началось');
function saveLog($data)
{
	// file_put_contents('/home/bitrix/ext_www/optid.ru/local/scripts/parserSadovod/log_import.log', print_r($data, true)."\n", FILE_APPEND);
}
function send($ids, $idsOriginal = 0){

    $siteList = \Optid\Local\Project\Site::getList('ID', ['filter' => ['UF_ACTIVE' => true, 'ID' =>66]]);
    $urlSad = $siteList[66]['UF_URL'];

	$url = $urlSad."/api/importDelProduct.php";

	$post_data = [
	    "token" => "87634f34dkfjm84vwperjfcerhfcvfdg",
	    "ids" => implode(',', $ids)
	];
	if($idsOriginal){
		$post_data["original"] = implode(',', $idsOriginal);
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	// указываем, что у нас POST запрос
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 300);
	// добавляем переменные
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
$z = 0;
// while (true) {

if($idLink){
	$andIdLink = ' AND `ID` = '.$idLink;
	$limit = ' LIMIT 1';
}else{
	$andIdLink = '';
	$limit = '';
}
// $limit = ' LIMIT 10';
$strSql = "SELECT `link`.`UF_PROVIDER`, 
`link`.`UF_ACTIVE`, 
`link`.`ID` as `LINK_ID`,
`link`.`UF_STATUS` as `LINK_STATUS` 
FROM `linksparsing` `link` WHERE `UF_COUNT` > 0 AND `UF_STATUS` = '' ".$andIdLink." ORDER BY `ID` ASC".$limit;

echo '<pre>';
// $strSql = "SELECT `ID`, `UF_PROVIDER`, `UF_STATUS`, `UF_LINK` FROM `parservk` WHERE `UF_ID_PRODUCT` > 0 AND `UF_ID_PRODUCT` > ";
$res = $DB->Query($strSql, false, $err_mess.__LINE__);
// $arProviders = [];
$iii = 0;
while ($row = $res->Fetch())
{
	saveLog('***************************************************************************************************');
	saveLog(++$iii.' элемент цикла');
	$idsProductDel = []; //ID Товаров для распроданности
	$idsProductDelOriginal = []; //ID Товаров на садоводе для распроданности
		// print_r($row);
		// continue;
		// exit();
		// if(!$row['UF_ACTIVE']){
		// 	//так как нет доступа к группе(юзеру) хз че там
		// 	// $strSqlProv = 'UPDATE `linksparsing` SET `UF_STATUS` = "CLOSE" WHERE `ID` = '.$row['LINK_ID'];
		// 	// $resProv = $DB->Query($strSqlProv, false, $err_mess.__LINE__);

		// 	// $strSql = 'UPDATE `parservk` SET `UF_STATUS` = "" WHERE `UF_PROVIDER` = '.$row['UF_PROVIDER'];
		// 	// $resUpdate = $DB->Query($strSql, false, $err_mess.__LINE__);
		// 	echo $row['LINK_ID'].' Группа не доступна, ID поставщика: '.$row['UF_PROVIDER'];
		// 	exit();
		// }else{
		// 	//Получим все посты для удаления этого поставщика
		// 	
		saveLog('Поставщик '.$row['UF_PROVIDER'].' ID ссылки: '.$row['LINK_ID']);
			
			$idsPost = [];
			$numAll = 0; //всего постов;
			$numDel = 0; //постов для удаления

			$strSqlPosts = "SELECT `UF_STATUS`, `ID`, `UF_ID_PRODUCT`, `UF_LINK`, `UF_PROVIDER` FROM `parservk` WHERE `UF_ID_PRODUCT` >  AND `UF_ID_LINK` = ".$row['LINK_ID'];
			$resPosts = $DB->Query($strSqlPosts, false, $err_mess.__LINE__);
			
			while ($rowPosts  = $resPosts->Fetch()) {
				if($rowPosts['UF_STATUS'] == 'DELETED'){
					continue;
				}
				$numAll++;
				if($rowPosts['UF_STATUS'] == 'DEL'){
					$idsProductDel[] = $rowPosts['UF_ID_PRODUCT'];
					$idsPost[] = $rowPosts['ID'];
					$numDel++;
				}

				//echo '<pre>';print_r($rowPosts);echo '</pre>';
			}

			saveLog('Постов: '.$numAll.' Удалять: '.$numDel);

			//если нет товаров для удаления, то переходим к след постащику
			if($numDel == 0){
				saveLog('Постов для удаления нет, переход к след.');
				continue;
			}
			// exit();

		if($numAll == $numDel && !$idLink){
			$strSqlProv = 'UPDATE `linksparsing` SET `UF_STATUS` = "0" WHERE `ID` = '.$row['LINK_ID'];
            $resProv = $DB->Query($strSqlProv, false, $err_mess.__LINE__);

            saveLog('Много для удаления');
			continue;

		}elseif($numAll * 0.8 < $numDel && !$idLink){
			//Удаляется больше половины товаров

			$strSqlProv = 'UPDATE `linksparsing` SET `UF_STATUS` = "1" WHERE `ID` = '.$row['LINK_ID'];
            $resProv = $DB->Query($strSqlProv, false, $err_mess.__LINE__);

	        saveLog('Много для удаления '.$numAll.' '.$numDel);
			continue;
		}

		//Проверка товаров на серии
		$arSeries = []; //серия для удаления
		if($idsProductDel){
			
			$arSelect = Array("ID", "NAME", "IBLOCK_ID", "PROPERTY_SERIES", "XML_ID");
			$arFilter = Array("IBLOCK_ID" => 40, "ID" => $idsProductDel);
			$resEl = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
			while($ob = $resEl->GetNextElement())
			{
			 	$arFields = $ob->GetFields();
			 	if($arFields['PROPERTY_SERIES_VALUE']){
			 		$arSeries[] = $arFields['PROPERTY_SERIES_VALUE'];
			 	}
			 	
			 	if($arFields['XML_ID']){
			 		$idsProductDelOriginal[]  = $arFields['XML_ID'];
			 	}	 
			 	
			 	// print_r($arFields);
			}
		}


		//получеие товаров серии
		if($arSeries){
			$arSelect = Array("ID", "NAME", "IBLOCK_ID", "PROPERTY_SERIES", "XML_ID");
			$arFilter = Array("IBLOCK_ID" => 11, 'PROPERTY_SERIES' => $arSeries);
			$resEl = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
			while($ob = $resEl->GetNextElement())
			{
			 	$arFields = $ob->GetFields();
			 	$idsProductDel[] = $arFields['ID'];	
			 	if($arFields['XML_ID']){
			 		$idsProductDelOriginal[] = $arFields['XML_ID'];	
			 	}
			 	
			 	// print_r($arFields);
			}
			// file_get_contents("https://api.telegram.org/bot400991748:AAE0X0kN5LEdsPPHlCBa3shO96-ipf02JYA". '/sendMessage?chat_id=190539045&text=Серии получены');
		}
		
		saveLog([$idsProductDel, $idsProductDelOriginal]);


		echo "\n".$row['LINK_ID'].' '."Всего постов: ".$numAll.' Удалять: '.$numDel.' Товаров: '.count(array_unique($idsProductDel)).' Поставщик:'.$row['UF_PROVIDER'];

	//простановка распроданности
	$GLOBALS['noSync'] = true;

	if(count(array_unique($idsProductDel)) > 3300){
		saveLog('Сработало ограничение на максимум '.count(array_unique($idsProductDel)).' пставщик '.$row['UF_PROVIDER']);
		echo 'Больше 300';
		exit();
	}
	if($idsProductDel){
		saveLog('Распродажа');
		$fp = fopen('/home/bitrix/ext_www/optid.ru/local/scripts/parserSadovod/soldProduct_'.date('dmY').'.csv', 'a');
		foreach (array_unique($idsProductDel) as $key => $id) {
			CIBlockElement::SetPropertyValuesEx($id, 44, [2352 => 238194, 2367 => time() - 60*60*24*30]);
			//ИД в файл
			fputcsv($fp, [$id]);
			//запись в историю
			$DB->PrepareFields("historychanges");
	        $arFields = [
	            "UF_DATE"              => $DB->CurrentTimeFunction(),
	            "UF_USER_ID"           => 1,
	            "UF_ELEMENT_ID"        => $id,
	            "UF_PROPERTY_CODE"     => '"SOLD"',
	            "UF_VALUE_AFTER"       => '"Y"',
	            "UF_VALUE_BEFORE"      => '"N"',
	            "UF_SOURCE"            => '"vk"'

	            ];

	        $ID = $DB->Insert("historychanges", $arFields, $err_mess.__LINE__);
		}
		fclose($fp);


		//Меняем статус в таблице ссылок
		$strSql = 'UPDATE `parservk` SET `UF_STATUS` = "DELETED" WHERE `ID` IN ('.implode(',', $idsPost).')';
		$resResult = $DB->Query($strSql, false, $err_mess.__LINE__);


		$DB->PrepareFields("statdeletepostsvk");
        $arFields = [
            "UF_DATE"              => $DB->CurrentTimeFunction(),
            "UF_LINK"              => $row['LINK_ID'],
            "UF_PROVIDER"          => $row['UF_PROVIDER'],
            "UF_DELETE_VK"         => $numDel,
            "UF_DELETED"           => $numDel,
            "UF_DELETE_PRODUCT"    => count(array_unique($idsProductDel)),

            ];

        $ID = $DB->Insert("statdeletepostsvk", $arFields, $err_mess.__LINE__);

		// file_get_contents("https://api.telegram.org/bot400991748:AAE0X0kN5LEdsPPHlCBa3shO96-ipf02JYA". '/sendMessage?chat_id=190539045&text=Статусы ссылок'.$numDel);


		// print_r(send($idsProductDel));
		// echo 
		if($idsProductDel){
			saveLog('Отправка на садовод');
			while (true) {
				
				$resSend = send($idsProductDel, $idsProductDelOriginal);
				saveLog(['Результат запроса', $resSend]);
				// print_r($res);
				if($resSend == '200'){
					saveLog('Условие для выхода из цикла');
					break;
				}
				saveLog('Невышли из цикла отправок');
				sleep(30);
				saveLog('Прошло 30 сек, еще запрос');
				
			}
		}
	}
}

	// if($z++ > 100){
	// 	break;
	// }
	

echo 'Time work: '.round(microtime(true) - $start, 4).' s '.$numPost."\n";
