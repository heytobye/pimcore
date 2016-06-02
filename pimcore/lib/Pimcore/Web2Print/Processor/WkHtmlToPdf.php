<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Web2Print\Processor;

use Pimcore\Config;
use \Pimcore\Model\Document;
use Pimcore\Web2Print\Processor;

class WkHtmlToPdf extends Processor
{

    /**
     * @var string
     */
    private $wkhtmltopdfBin;

    /**
     * @var string
     */
    private $options;


    /**
     * @param string $wkhtmltopdfBin
     * @param array $options key => value
     */
    public function __construct($wkhtmltopdfBin = null, $options = null)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        if (empty($wkhtmltopdfBin)) {
            $this->wkhtmltopdfBin = $web2printConfig->wkhtmltopdfBin;
        } else {
            $this->wkhtmltopdfBin = $wkhtmltopdfBin;
        }

        if (empty($options)) {
            if ($web2printConfig->wkhtml2pdfOptions) {
                $options = $web2printConfig->wkhtml2pdfOptions->toArray();
            }
        }

        if ($options) {
            foreach ($options as $key => $value) {
                $this->options = " --" . (string)$key;
                if ($value !== null && $value !== "") {
                    $this->options .= " " . (string)$value;
                }
            }
        } else {
            $this->options = "";
        }
    }

    protected function buildPdf(Document\PrintAbstract $document, $config)
    {
        $params = [];
        $html = $document->renderDocument($params);
        $placeholder = new \Pimcore\Placeholder();
        $html = $placeholder->replacePlaceholders($html);

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html", $html);
        $pdf = $this->fromStringToStream($html);

        return $pdf;
    }

    public function getProcessingOptions()
    {
        return [];
    }


    /**
     * @param string $htmlString
     * @param string $dstFile
     * @return string
     */
    public function fromStringToFile($htmlString, $dstFile = null)
    {
        $id = uniqid();
        $tmpHtmlFile = WEB2PRINT_WKHTMLTOPDF_TEMP_PATH . "/" . $id . ".htm";
        file_put_contents($tmpHtmlFile, $htmlString);
        $srcUrl = $this->getTempFileUrl() . "?id=" . $id;

        $pdfFile = $this->convert($srcUrl, $dstFile);

        @unlink($tmpHtmlFile);

        return $pdfFile;
    }

    /**
     * @param string $htmlString
     * @return string
     */
    public function fromStringToStream($htmlString)
    {
        $tmpFile = $this->fromStringToFile($htmlString);
        $stream = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $stream;
    }


    /**
     * @throws \Exception
     * @param string $srcFile
     * @param string $dstFile
     * @return string
     */
    protected function convert($srcUrl, $dstFile = null)
    {
        $outputFile = WEB2PRINT_WKHTMLTOPDF_TEMP_PATH . "/wkhtmltopdf.out";
        if (empty($dstFile)) {
            $dstFile = WEB2PRINT_WKHTMLTOPDF_TEMP_PATH . "/" . uniqid() . ".pdf";
        }

        if (empty($srcUrl) || empty($dstFile) || empty($this->wkhtmltopdfBin)) {
            throw new \Exception("srcUrl || dstFile || wkhtmltopdfBin is empty!");
        }

        $retVal = 0;
        $cmd = $this->wkhtmltopdfBin . " " . $this->options . " " . escapeshellarg($srcUrl) . " " . escapeshellarg($dstFile) . " > " . $outputFile;
        system($cmd, $retVal);
        $output = file_get_contents($outputFile);
        @unlink($outputFile);

        if ($retVal != 0 && $retVal != 2) {
            throw new \Exception("wkhtmltopdf reported error (" . $retVal . "): \n" . $output . "\ncommand was:" . $cmd);
        }

        return $dstFile;
    }

    public static function getTempFileUrl()
    {
        $web2printConfig = Config::getWeb2PrintConfig();
        if ($web2printConfig->hostname) {
            $hostname = $web2printConfig->wkhtml2pdfHostname;
        } elseif (\Pimcore\Config::getSystemConfig()->general->domain) {
            $hostname = \Pimcore\Config::getSystemConfig()->general->domain;
        } else {
            $hostname = $_SERVER["HTTP_HOST"];
        }

        $protocol = $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
        return $protocol . "://" . $hostname . "/plugin/Web2Print/temp-file/get";
    }
}