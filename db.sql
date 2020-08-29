createdb kabus_db -E UTF-8 -U postgres
createdb -U postgres kabus_db


CREATE TABLE symbols(
id serial primary key,
reg_date date,
Symbol varchar(255),
SymbolName varchar(255),
Exchange int,
ExchangeName varchar(255),
CurrentPrice double precision,
CurrentPriceTime timestamp,
CurrentPriceChangeStatus varchar(255), 
CurrentPriceStatus int,
CalcPrice double precision,
PreviousClose double precision,
PreviousCloseTime timestamp,
ChangePreviousClose double precision,
ChangePreviousClosePer double precision,
OpeningPrice double precision,
OpeningPriceTime timestamp,
HighPrice double precision,
HighPriceTime timestamp,
LowPrice double precision,
LowPriceTime timestamp,
TradingVolume double precision,
TradingVolumeTime timestamp,
VWAP double precision,
TradingValue double precision,
BidQty double precision,
BidPrice double precision,
BidTime timestamp,
BidSign varchar(255),
MarketOrderSellQty double precision,
MarketOrderBuyQty double precision,
OverSellQty double precision,
UnderBuyQty double precision,
TotalMarketValue double precision
);

