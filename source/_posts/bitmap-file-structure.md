---
title: BMP (Bitmap) 檔案格式
tags: [Bitmap]
date: 2020-09-01 10:37:48
category: Note
---
點陣圖（`.bmp`）是 windows 使用的圖像格式，整個檔案由四個部分組成：[^1]
- Bitmap File Header
- Bitmap Info Header
- Color Table (Palette)
- Bitmap Array

[^1]:[點陣圖（Bitmap）檔案格式 @ 瘋小貓的華麗冒險](https://crazycat1130.pixnet.net/blog/post/1345538)

## 1. Bitmap File Header 
| Start  | Name      | Size (Byte) | Content    |
| ------ | --------- | ----------- | ---------- |
| 0x0000 | ID        | 2           | "BM"       |
| 0x0002 | File Size | 4           | Total file size |
| 0x0004 | Reserved  | 4           | Reserved   |
| 0x000A | Bitmap Data Offset | 4  | BMP offset |

- ID 欄位為識別碼，對應以下值：[^2]
    - BM – Windows 3.1x, 95, NT, ... etc.
    - BA – OS/2 struct Bitmap Array
    - CI – OS/2 struct Color Icon
    - CP – OS/2 const Color Pointer
    - IC – OS/2 struct Icon
    - PT – OS/2 Pointer
- Bitmap Data Offset：點陣圖資料（像素陣列）的位址偏移，也就是起始位址。

[^2]:[BMP - 維基百科，自由的百科全書](https://zh.wikipedia.org/wiki/BMP)

## 2.Bitmap Info Header
| Start  | Name      | Size (Byte) | Content    |
| ------ | --------- | ----------- | ---------- |
| 0x000E | Bitmap Header Size | 4  | BIH size   |
| 0x0012 | Width     | 4           | BMP width  (pixel) |
| 0x0016 | Height    | 4           | BMP height (pixel) |
| 0x001A | Planes    | 2           | BMP plane counts   |
| 0x001C | Bits Per Pixel | 2      | Pixel size |
| 0x001E | Compression | 4         | Compression method |
| 0x0022 | Bitmap Data Size | 4    | BMP data size |
| 0x0026 | H-Resolution | 4     | Horizontal Resolution |
| 0x002A | V-Resolution | 4     | Vertical Resolution   |
| 0x002E | Used Colors  | 4     | Palette colors used   |
| 0x0032 | Important Colors | 4 | Important color count |

- 高度為帶號值
    - 若為正數，代表圖為倒向
    - 若為負數，代表圖為正向 [^3]
- Planes 為圖層數，不過永遠設成 1
- Bits/pixel 有 6 種不同方式:
    - 1：單色點陣圖（使用 2 色調色盤）
	- 4：4 位元點陣圖（使用 16 色調色盤）
	- 8：8 位元點陣圖（使用 256 色調色盤）
	- 16：16 位元高彩點陣圖（不一定使用調色盤）
	- 24：24 位元全彩點陣圖（不使用調色盤）
	- 32：32 位元全彩點陣圖（不一定使用調色盤）
- 壓縮方式有 4 種
    - 0：未壓縮，不使用調色盤
	- 1：RLE 8-bit/pixel
	- 2：RLE 4-bit/pixel
	- 3：Bitfields

[^3]:[BMP檔案格式詳解（BMP file format）[圖文解說] - IT閱讀](https://www.itread01.com/content/1549504280.html)

## 3.Palette
| Start  | Name    | Size (Byte) | Content      |
| ------ | ------- | ----------- | ------------ |
| 0x0036 | Palette | N\*4        | Palette data |

每個索引值表示一個顏色：`0x00RRGGBB`，最高位保留 0

## 4.Bitmap Array
| Start  | Name        | Size (Byte) | Content      |
| ------ | ----------- | ----------- | ------------ |
| -      | Bitmap Data | -           | BMP data     |

根據 Height 設定的值不同，掃描的方向也不同；若為正向則為由下到上，反之亦然。而每個掃描列須為**4 Bytes 的倍數**。
