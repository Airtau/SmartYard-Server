<?php

namespace hw\SmartConfigurator\DbConfigCollector;

use hw\SmartConfigurator\ConfigurationBuilder\CameraConfigurationBuilder;

/**
 * This class is responsible for collecting camera configuration data from the database.
 */
class CameraDbConfigCollector implements IDbConfigCollector
{

    /**
     * @var array The application configuration.
     */
    private array $appConfig;

    /**
     * @var array The camera data.
     */
    private array $cameraData;

    /**
     * @var CameraConfigurationBuilder The builder used to construct camera configuration.
     */
    private CameraConfigurationBuilder $builder;

    /**
     * Construct a new CameraDbConfigCollector instance.
     *
     * @param array $appConfig The application configuration.
     * @param array $cameraData The camera data.
     */
    public function __construct(array $appConfig, array $cameraData)
    {
        $this->appConfig = $appConfig;
        $this->cameraData = $cameraData;
        $this->builder = new CameraConfigurationBuilder();
    }

    public function collectConfig(): array
    {
        $this
            ->addEventServer()
            // FIXME: temporarily ignore motion detection settings
            // ->addMotionDetection()
            ->addNtp()
            ->addOsdText()
        ;

        return $this->builder->getConfig();
    }

    /**
     * Add the event server information to the camera configuration.
     *
     * @return self
     */
    private function addEventServer(): self
    {
        $url = $this->appConfig['syslog_servers'][$this->cameraData['json']['eventServer']][0];
        $this->builder->addEventServer($url);
        return $this;
    }

    /**
     * Add motion detection settings to the camera configuration.
     *
     * @return self
     */
    private function addMotionDetection(): self
    {
        [
            'mdLeft' => $left,
            'mdTop' => $top,
            'mdWidth' => $width,
            'mdHeight' => $height,
        ] = $this->cameraData;

        $this->builder->addMotionDetection($left, $top, $width, $height);
        return $this;
    }

    /**
     * Add NTP settings to the camera configuration.
     *
     * @return self
     */
    private function addNtp(): self
    {
        $url = $this->appConfig['ntp_servers'][0];
        $urlParts = parse_url_ext($url);
        $timezone = $this->cameraData['timezone'];

        if ($timezone === '-') {
            $timezone = 'Europe/Moscow';
        }

        $this->builder->addNtp($urlParts['host'], $urlParts['port'], $timezone);
        return $this;
    }

    /**
     * Add OSD text settings to the camera configuration.
     *
     * @return void
     */
    private function addOsdText(): void
    {
        $this->builder->addOsdText($this->cameraData['name']);
    }
}
