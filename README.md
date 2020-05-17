# alieveo:bitrix24.users.list

Ознакомиться с техническим заданием, способом решения и оценкой можно по [ссылке](./PRD.md).

## Как использовать?

1. Склонируйте гит репозиторий [bitrix24.users.list](https://github.com/eoaliev/bitrix24.users.list) в папку `document_root/bitrix/components/eoaliev/bitrix24.users.list`
```bash
mkdir -p document_root/bitrix/components/eoaliev &&\
cd document_root/bitrix/components/eoaliev &&\
git clone git@github.com:eoaliev/bitrix24.users.list.git
```

Либо можно склонировать в папку `document_root/local/components/eoaliev/bitrix24.users.list`
```bash
mkdir -p document_root/local/components/eoaliev &&\
cd document_root/local/components/eoaliev &&\
git clone git@github.com:eoaliev/bitrix24.users.list.git
```

2. В нужном файле подключите компонент
```php
$APPLICATION->IncludeComponent(
    'eoaliev:bitrix24.users.list',
    '',
    [
        'PATH_TO_USER' => 'company/personal/user/#USER_ID#/',
    ]
);
```
