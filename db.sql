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

CREATE TABLE items(
id serial primary key,
reg_date date,
loop int,
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
TotalMarketValue double precision,
inclination double precision,
intercept double precision,
AskQty double precision,
AskPrice double precision,
AskTime timestamp,
AskSign varchar(255),
Sell1_Price double precision, 
Sell1_Qty double precision, 
Sell2_Price double precision, 
Sell2_Qty double precision ,
Sell3_Price double precision, 
Sell3_Qty double precision, 
Sell4_Price double precision, 
Sell4_Qty double precision, 
Sell5_Price double precision, 
Sell5_Qty double precision, 
Sell6_Price double precision, 
Sell6_Qty double precision, 
Sell7_Price double precision, 
Sell7_Qty double precision, 
Sell8_Price double precision, 
Sell8_Qty double precision, 
Sell9_Price double precision, 
Sell9_Qty double precision, 
Sell10_Price double precision,
Sell10_Qty double precision, 
Buy1_Price double precision, 
Buy1_Qty double precision,
Buy2_Price double precision, 
Buy2_Qty double precision,
Buy3_Price double precision, 
Buy3_Qty double precision,
Buy4_Price double precision, 
Buy4_Qty double precision,
Buy5_Price double precision, 
Buy5_Qty double precision,
Buy6_Price double precision, 
Buy6_Qty double precision,
Buy7_Price double precision, 
Buy7_Qty double precision,
Buy8_Price double precision, 
Buy8_Qty double precision,
Buy9_Price double precision, 
Buy9_Qty double precision,
Buy10_Price double precision,
Buy10_Qty double precision,
created timestamp
);

ALTER TABLE items add column inclination double precision;
ALTER TABLE items add column intercept double precision;
ALTER TABLE items add column AskQty double precision;
ALTER TABLE items add column AskPrice double precision;
ALTER TABLE items add column AskTime timestamp;
ALTER TABLE items add column AskSign varchar(255);

ALTER TABLE items add column Sell1_Price double precision;
ALTER TABLE items add column Sell1_Qty double precision;
ALTER TABLE items add column Sell2_Price double precision;
ALTER TABLE items add column Sell2_Qty double precision;
ALTER TABLE items add column Sell3_Price double precision;
ALTER TABLE items add column Sell3_Qty double precision;
ALTER TABLE items add column Sell4_Price double precision;
ALTER TABLE items add column Sell4_Qty double precision;
ALTER TABLE items add column Sell5_Price double precision;
ALTER TABLE items add column Sell5_Qty double precision;
ALTER TABLE items add column Sell6_Price double precision;
ALTER TABLE items add column Sell6_Qty double precision;
ALTER TABLE items add column Sell7_Price double precision;
ALTER TABLE items add column Sell7_Qty double precision;
ALTER TABLE items add column Sell8_Price double precision;
ALTER TABLE items add column Sell8_Qty double precision;
ALTER TABLE items add column Sell9_Price double precision;
ALTER TABLE items add column Sell9_Qty double precision;
ALTER TABLE items add column Sell10_Price double precision;
ALTER TABLE items add column Sell10_Qty double precision;

ALTER TABLE items add column Buy1_Price double precision;
ALTER TABLE items add column Buy1_Qty double precision;
ALTER TABLE items add column Buy2_Price double precision;
ALTER TABLE items add column Buy2_Qty double precision;
ALTER TABLE items add column Buy3_Price double precision;
ALTER TABLE items add column Buy3_Qty double precision;
ALTER TABLE items add column Buy4_Price double precision;
ALTER TABLE items add column Buy4_Qty double precision;
ALTER TABLE items add column Buy5_Price double precision;
ALTER TABLE items add column Buy5_Qty double precision;
ALTER TABLE items add column Buy6_Price double precision;
ALTER TABLE items add column Buy6_Qty double precision;
ALTER TABLE items add column Buy7_Price double precision;
ALTER TABLE items add column Buy7_Qty double precision;
ALTER TABLE items add column Buy8_Price double precision;
ALTER TABLE items add column Buy8_Qty double precision;
ALTER TABLE items add column Buy9_Price double precision;
ALTER TABLE items add column Buy9_Qty double precision;
ALTER TABLE items add column Buy10_Price double precision;
ALTER TABLE items add column Buy10_Qty double precision;

ALTER TABLE items add column created timestamp;
