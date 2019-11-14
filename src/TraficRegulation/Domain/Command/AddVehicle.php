<?php
declare(strict_types=1);

namespace App\TraficRegulation\Domain\Command;

use App\TraficRegulation\Domain\Model\VehicleFleetId;

final class AddVehicle
{
    private $vehicleFleetId;
    private $name;

    public function __construct(VehicleFleetId $vehicleFleetId, string $name)
    {
        $this->vehicleFleetId = $vehicleFleetId;
        $this->name = $name;
    }

    public function vehicleFleetId(): VehicleFleetId
    {
        return $this->vehicleFleetId;
    }

    public function name(): string
    {
        return $this->name;
    }
}
