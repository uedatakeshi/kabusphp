<?php


$json = @file_get_contents("sample.json");
$item = json_decode($json);

$db = pg_connect("host=localhost dbname=kabus_db user=postgres password=pass123");

$today = date("Y-m-d");
foreach ($item as $k => $v) {
    $item[$k] = pg_escape_string($v);
}

$query = <<<END
    INSERT INTO symbols (
        reg_date,
        Symbol,
        SymbolName,
        Exchange,
        ExchangeName,
        CurrentPrice,
        CurrentPriceTime,
        CurrentPriceChangeStatus, 
        CurrentPriceStatus,
        CalcPrice,
        PreviousClose,
        PreviousCloseTime,
        ChangePreviousClose,
        ChangePreviousClosePer,
        OpeningPrice,
        OpeningPriceTime,
        HighPrice,
        HighPriceTime,
        LowPrice,
        LowPriceTime,
        TradingVolume,
        TradingVolumeTime,
        VWAP,
        TradingValue,
        BidQty,
        BidPrice,
        BidTime,
        BidSign,
        MarketOrderSellQty,
        MarketOrderBuyQty,
        OverSellQty,
        UnderBuyQty,
        TotalMarketValue 
    ) VALUES (
        '{$today}',
        '{$item['Symbol']}',
        '{$item['SymbolName']}',
        '{$item['Exchange']}',
        '{$item['ExchangeName']}',
        '{$item['CurrentPrice']}',
        '{$item['CurrentPriceTime']}',
        '{$item['CurrentPriceChangeStatus']}', 
        '{$item['CurrentPriceStatus']}',
        '{$item['CalcPrice']}',
        '{$item['PreviousClose']}',
        '{$item['PreviousCloseTime']}',
        '{$item['ChangePreviousClose']}',
        '{$item['ChangePreviousClosePer']}',
        '{$item['OpeningPrice']}',
        '{$item['OpeningPriceTime']}',
        '{$item['HighPrice']}',
        '{$item['HighPriceTime']}',
        '{$item['LowPrice']}',
        '{$item['LowPriceTime']}',
        '{$item['TradingVolume']}',
        '{$item['TradingVolumeTime']}',
        '{$item['VWAP']}',
        '{$item['TradingValue']}',
        '{$item['BidQty']}',
        '{$item['BidPrice']}',
        '{$item['BidTime']}',
        '{$item['BidSign']}',
        '{$item['MarketOrderSellQty']}',
        '{$item['MarketOrderBuyQty']}',
        '{$item['OverSellQty']}',
        '{$item['UnderBuyQty']}',
        '{$item['TotalMarketValue']}' 
    ) 
END;
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
