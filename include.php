<?php

//http://dev.1c-bitrix.ru/community/webdev/group/78/blog/1657/
IncludeModuleLangFile(__FILE__);

class FIXcurrencyrate
{
	public static function OnAdminListDisplayHandler(&$list)
	{
		if ($GLOBALS['APPLICATION']->GetCurPage()=='/bitrix/admin/currencies_rates.php') {
			$list->context->items[-2] = array(
										'ICON' => 'btn_refresh',
										'TEXT' => GetMessage('FIXIT_ACTION_GET'),
										'TITLE' => GetMessage('FIXIT_ACTION_GET_TITLE'),
										'LINK' => $GLOBALS['APPLICATION']->GetCurPageParam('fixit_get_rate=Y', array('mode')),
									);
			$list->context->items[-1] = array('SEPARATOR' => 1);
			$list->context->items[-1] = array(
										'ICON' => 'btn_refresh',
										'TEXT' => GetMessage('FIXIT_ACTION_DELETE'),
										'TITLE' => GetMessage('FIXIT_ACTION_DELETE_TITLE'),
										'LINK' => $GLOBALS['APPLICATION']->GetCurPageParam('fixit_delete_rate=Y', array('mode')),
									);
			ksort($list->context->items);
		}
	}

	public static function OnBeforePrologHandler()
	{
		if ($GLOBALS['APPLICATION']->GetCurPage()=='/bitrix/admin/currencies_rates.php' && $_REQUEST['fixit_get_rate']=='Y' &&
			$GLOBALS['APPLICATION']->GetGroupRight('currency')>'D'
		) {
			self::UpdateRates(false);
			LocalRedirect($GLOBALS['APPLICATION']->GetCurPageParam('', array('fixit_get_rate')));
		}
		
		if ($GLOBALS['APPLICATION']->GetCurPage()=='/bitrix/admin/currencies_rates.php' && $_REQUEST['fixit_delete_rate']=='Y' &&
			$GLOBALS['APPLICATION']->GetGroupRight('currency')>'D'
		) {
			self::DeleteRates(false);
			LocalRedirect($GLOBALS['APPLICATION']->GetCurPageParam('', array('fixit_delete_rate')));
		}
	}

	public static function UpdateRates($bAgent=true)
	{
		if (CModule::IncludeModule('currency')) {

			$arCurr = array();
			$rsRate = CCurrency::GetList($by='currency', $order='asc');
			while ($arRate = $rsRate->Fetch()) {
				if ($arRate['CURRENCY']!='KZT') {
					$arCurr[] = $arRate['CURRENCY'];
				}
			}
			/* получаем текущее ID курса 
			$arID = array();
			$db_rate = CCurrencyRates::GetList($by='id', $order='desc', array());
			while($ar_rate = $db_rate->Fetch())
			{
				$arID[] = $ar_rate['ID'];
			}
			*/
			if (!empty($arCurr)) {
				$adminDate = date($GLOBALS['DB']->DateFormatToPHP(CLang::GetDateFormat('SHORT')));
				
				$url = "http://www.nationalbank.kz/rss/rates_all.xml";
				$dataObj = simplexml_load_file($url);
				
					if ($dataObj){
					$percent = intval(COption::GetOptionString('fixit.currencyrate', 'percent'));
					foreach ($dataObj->channel->item as $item){
						/*echo "Валюта: ".$item->title."<br>";
						echo "Дата публикации: ".$item->pubDate."<br>";
						echo "Текущий курс: ".$item->description."<br>";
						echo "Номинал: ".$item->quant."<br>";*/
						$title = $item->title;
						$pubDate = $item->pubDate;
						$description = $item->description;
						$quant = $item->quant;
						
							if (in_array($title, $arCurr)) {
								$rate = doubleval(str_replace(',', '.', $description));
								$val = $rate + (($rate * $percent) / 100);
								$arNewRate = array(
														'CURRENCY' => $title,
														'RATE_CNT' => intval($quant),
														'RATE' => $description,
														'DATE_RATE' => $adminDate,
													);
													if(!CCurrencyRates::GetList($by='id', $order='desc', $arNewRate)->Fetch()){
														CCurrencyRates::Add($arNewRate);
														}
													/*
													if (!CCurrencyRates::GetList($by='id', $order='desc', $arNewRate)->Fetch()) {
														CCurrencyRates::Update($arID, $arNewRate);
													}*/
													
							}
					}
				}
			}
		}
		if ($bAgent) {
			return 'FIXcurrencyrate::UpdateRates();';
		}
	}
	
	public static function DeleteRates()
	{
		if (CModule::IncludeModule('currency')){
			$idRate = array();
			$adminDate = date($GLOBALS['DB']->DateFormatToPHP(CLang::GetDateFormat('SHORT')));
			$arFilter = array(
				"!DATE_RATE" => $adminDate
			);
			$by = "date";
			$order = "desc";
			$db_rate = CCurrencyRates::GetList($by, $order, $arFilter);
				while($ar_rate = $db_rate->Fetch())
				{
					$idRate[] = $ar_rate["ID"];
					/*echo $ar_rate["DATE_RATE"]." ".$ar_rate["CURRENCY"]." ".$ar_rate["RATE"]."<br>";*/
				}
			
				foreach($idRate as $item){
					CCurrencyRates::Delete($item);
				}
		} 
	}
}