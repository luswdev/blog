---
title: "計概：資訊系統"
date: 2018-02-09 10:18:29 +0800
tag: 計算機概論
category: 計算機概論
---
##  管理資訊
有三種普遍的資訊系統：電子試算表、資料庫管理系統及電子商務。

##  試算表
試算表 spreadsheet 允許使用者以標籤畫的儲存格來組織、分析資料，一個儲存格可儲存資料，如數字、文字等，或公式求值。
試算表通常以字母表示直欄 column，以數字表示橫列 row，如A5、B7等。

- 試算表公式：如SUM、COUNT、MAX等。
- 循環參照：一個無解狀態，如B14 = D21+D22，而D21 = B13+B14。

---
##  資料庫系統
- 資料庫 database：一套結構化的資料。
- 資料庫管理系統 DBMS：軟體和資料的結合，有三大要素：
	1. 實體資料庫
	2. 資料庫引擎：配合資料庫語言，可指定資料的結構、新增、修改、刪除及查詢 query 資料。
	3. 資料庫綱要 schema：提供資料庫中資料的邏輯綱要。

### 關聯式模型 relational model

資料項目與他們的關係組織成資料表 table，資料表示一些紀錄的集合 record，一筆紀錄是一些相關欄位 field 的集合，一個欄位對應一個值。


一筆紀錄稱作一個資料庫物件或是一個實體 entity，一筆紀錄的欄位稱作資料庫物件的屬性 attribute。

關鍵欄位 key：用來識別資料表中的每個紀錄。

### 結構化查詢語言 SQL
用來管理關聯式資料庫的語言，包含了規範 schema、新增、修改、刪除及查詢資料。

- 查詢：

```sql 
SELECT attribute-list FROM table-list WHERE condition
```
如欲取回整個紀錄，可以用 *，需要字串比對，可以用 like：

```sql 
SELECT * FROM table-list WHERE condition like '%doc%'
```
也可以用 order by 來排序

```sql 
SELECT attribute-list FROM table-list WHERE condition order by id
```

- 修改：

```sql
insert into attribute-list values (some , 'value')
```

```sql
update attribute-list set field_name = 'value' where another_field = 'value2'
```

```sql
delete from attribute-list where field_name = 'value'
```
### 資料庫的設計
- 實體 — 關係 ER 模型、ER圖
- 一對一、一對多、多對多