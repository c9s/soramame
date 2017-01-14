
## Usage

```php
use CLIFramework\Logger;
use CurlKit\CurlAgent;
use Soramame\SoramameAgent;

$logger = new Logger;
$curlAgent = new CurlAgent;
$agent = new SoramameAgent($curlAgent, $logger);

$attributes = $agent->fetchStationAttributes();
print_r($attributes);

$counties = $agent->fetchCountyList();
foreach ($counties as $countyId => $countyName) {
    $stations = $agent->fetchCountyStations($countyId, $attributes);
    foreach ($stations as $station) {
        $history = $agent->fetchStationHistory($station['code']);
    }
}
```

## Install

```
composer require c9s/soramame "1.0.x-dev"
```

## Example

Please take a look at `example.php`

## Development

```
composer install --prefer-source
```

## API

### SoramameAgent::fetchStationAttributes

```
Array
(
    [0] => SO2
    [1] => NO
    [2] => NO2
    [3] => NOX
    [4] => CO
    [5] => OX
    [6] => NMHC
    [7] => CH4
    [8] => THC
    [9] => SPM
    [10] => PM2.5
    [11] => SP
    [12] => WD
    [13] => WS
    [14] => TEMP
    [15] => HUM
)
```


### SoramameAgent::fetchCountyList

```
Parsing station attributes http://soramame.taiki.go.jp/MstItiranTitle.php?Time=2017011412
Array
(
    [01] => 北海道
    [02] => 青森県
    [03] => 岩手県
    [04] => 宮城県
    [05] => 秋田県
    [06] => 山形県
    [07] => 福島県
    [08] => 茨城県
    [09] => 栃木県
    [10] => 群馬県
    [11] => 埼玉県
    [12] => 千葉県
    [13] => 東京都
    [14] => 神奈川県
    [15] => 新潟県
    [16] => 富山県
    [17] => 石川県
    [18] => 福井県
    [19] => 山梨県
    [20] => 長野県
    [21] => 岐阜県
    [22] => 静岡県
    [23] => 愛知県
    [24] => 三重県
    [25] => 滋賀県
    [26] => 京都府
    [27] => 大阪府
    [28] => 兵庫県
    [29] => 奈良県
    [30] => 和歌山県
    [31] => 鳥取県
    [32] => 島根県
    [33] => 岡山県
    [34] => 広島県
    [35] => 山口県
    [36] => 徳島県
    [37] => 香川県
    [38] => 愛媛県
    [39] => 高知県
    [40] => 福岡県
    [41] => 佐賀県
    [42] => 長崎県
    [43] => 熊本県
    [44] => 大分県
    [45] => 宮崎県
    [46] => 鹿児島県
    [47] => 沖縄県
)
```

### SoramameAgent::fetchCountyStations($countyId, array $attributes)

```
Array
(
    [0] => Array
        (
            [code] => 01101010
            [name] => センター
            [address] => 札幌市中央区北１西２
            [attributes] => Array
                (
                    [SO2] => 1
                    [NO] => 1
                    [NO2] => 1
                    [NOX] => 1
                    [CO] =>
                    [OX] => 1
                    [NMHC] => 1
                    [CH4] => 1
                    [THC] => 1
                    [SPM] => 1
                    [PM2.5] =>
                    [SP] =>
                    [WD] =>
                    [WS] =>
                    [TEMP] =>
                    [HUM] =>
                )

        )
    ....
```


### SoramameAgent::fetchStationHistory(string stationCode)

```
Array
(
    [0] => Array
        (
            [so2] => 0.007
            [no] => 0.084
            [no2] => 0.05
            [nox] => 0.134
            [ox] => 0.01
            [nmhc] => 0.26
            [ch4] => 2.23
            [thc] => 2.49
            [spm] => 0.036
            [published_at] => 2017-01-14T12:00:00+09:00
        )

    [1] => Array
        (
            [so2] => 0.007
            [no] => 0.153
            [no2] => 0.067
            [nox] => 0.22
            [ox] => 0.007
            [nmhc] => 0.35
            [ch4] => 2.21
            [thc] => 2.56
            [spm] => 0.053
            [published_at] => 2017-01-14T11:00:00+09:00
        )

    [2] => Array
        (
            [so2] => 0.007
            [no] => 0.101
            [no2] => 0.05
            [nox] => 0.151
            [ox] => 0.006
            [nmhc] => 0.24
            [ch4] => 2.05
            [thc] => 2.29
            [spm] => 0.023
            [published_at] => 2017-01-14T10:00:00+09:00
        )

    [3] => Array
        (
            [so2] => 0.008
            [no] => 0.081
            [no2] => 0.045
            [nox] => 0.126
            [ox] => 0.005
            [nmhc] => 0.22
            [ch4] => 2.1
            [thc] => 2.32
            [spm] => 0.015
            [published_at] => 2017-01-14T09:00:00+09:00
        )

    [4] => Array
        (
            [so2] => 0.006
            [no] => 0.053
            [no2] => 0.041
            [nox] => 0.094
            [ox] => 0.003
            [nmhc] => 0.15
            [ch4] => 2.06
            [thc] => 2.21
            [spm] => 0.011
            [published_at] => 2017-01-14T08:00:00+09:00
        )
```
