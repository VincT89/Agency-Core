<?php

namespace App\Services\Integrations\Nextcloud;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NextcloudService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $webdavPath;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.nextcloud.base_url', env('NEXTCLOUD_BASE_URL', '')), '/');
        $this->username = config('services.nextcloud.username', env('NEXTCLOUD_USERNAME', ''));
        $this->password = config('services.nextcloud.password', env('NEXTCLOUD_PASSWORD', ''));
        $this->webdavPath = config('services.nextcloud.webdav_path', env('NEXTCLOUD_WEBDAV_PATH', '/remote.php/dav/files'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->username) && !empty($this->password);
    }

    /**
     * Elenca i file e le cartelle in un determinato percorso.
     * Mostra cartelle e filtra i file limitandosi alle immagini.
     */
    public function listFiles(string $path = '/'): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $path = '/' . trim($path, '/');
        $fullPath = $this->webdavPath . '/' . $this->username . $path;
        // La root dav/files finisce con /username/

        $url = $this->baseUrl . $fullPath;

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Depth' => '1',
                ])
                ->send('PROPFIND', $url, [
                    'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontenttype/><d:getcontentlength/><d:getlastmodified/></d:prop></d:propfind>'
                ]);

            if (!$response->successful()) {
                Log::error('Nextcloud PROPFIND fallito: ' . $response->body());
                return [];
            }

            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            $namespaces = $xml->getNamespaces(true);
            $results = [];

            // Il primo elemento è sempre la directory corrente
            $isFirst = true;

            foreach ($xml->response as $res) {
                if ($isFirst) {
                    $isFirst = false;
                    continue; // salta la directory corrente
                }

                $href = (string)$res->href;
                // decodifica %20 ecc.
                $href = urldecode($href);
                $hrefPath = Str::after($href, $this->webdavPath . '/' . $this->username);

                $propstat = $res->propstat;
                $prop = $propstat->prop->children($namespaces['d']);
                
                $isDir = isset($prop->resourcetype->collection);
                $contentType = (string)$prop->getcontenttype;
                $size = (int)$prop->getcontentlength;
                
                $name = basename($href);

                // Filtro: mostriamo le cartelle, ma per i file mostriamo solo le immagini supportate
                if (!$isDir) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    
                    if (!in_array($ext, $allowedExts) || !in_array(strtolower($contentType), $allowedMimes)) {
                        continue;
                    }
                }

                $results[] = [
                    'name' => $name,
                    'path' => $hrefPath,
                    'is_dir' => $isDir,
                    'size' => $size,
                    'content_type' => $contentType,
                ];
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Errore connessione Nextcloud: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Scarica un file da Nextcloud e restituisce il contenuto.
     */
    public function downloadFile(string $remotePath): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $remotePath = '/' . trim($remotePath, '/');
        $url = $this->baseUrl . $this->webdavPath . '/' . $this->username . $remotePath;

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
            
            Log::error('Errore download file Nextcloud: ' . $response->status() . ' - ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Errore download Nextcloud: ' . $e->getMessage());
            return null;
        }
    }
}
