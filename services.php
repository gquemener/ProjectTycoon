<?php

use App\ServiceBus\CommandBus;
use App\ServiceBus\EventBus;
use App\Tracking\Domain\Command\LoadCargo;
use App\Tracking\Domain\Command\LoadCargoHandler;
use App\Tracking\Domain\Command\RegisterCargoInTheFacility;
use App\Tracking\Domain\Command\RegisterCargoInTheFacilityHandler;
use App\Tracking\Domain\Command\UnloadCargo;
use App\Tracking\Domain\Command\UnloadCargoHandler;
use App\Tracking\Domain\Event\CargoWasLoaded;
use App\Tracking\Domain\Event\CargoWasRegistered;
use App\Tracking\Domain\Event\CargoWasUnloaded;
use App\Tracking\Domain\Model\CargoRepository;
use App\Tracking\Domain\ProcessManager\CargoHandler;
use App\Tracking\Infrastructure\InMemoryCargoRepository;
use App\TraficRegulation\Domain\Command\AddVehicle;
use App\TraficRegulation\Domain\Command\AddVehicleHandler;
use App\TraficRegulation\Domain\Command\ComputeVehicleDestination;
use App\TraficRegulation\Domain\Command\ComputeVehicleDestinationHandler;
use App\TraficRegulation\Domain\Command\ComputeVehicleRoute;
use App\TraficRegulation\Domain\Command\ComputeVehicleRouteHandler;
use App\TraficRegulation\Domain\Command\CreateVehicleFleet;
use App\TraficRegulation\Domain\Command\CreateVehicleFleetHandler;
use App\TraficRegulation\Domain\Command\RepositionVehicleFleet;
use App\TraficRegulation\Domain\Command\RepositionVehicleFleetHandler;
use App\TraficRegulation\Domain\Event\VehicleHasBeenAdded;
use App\TraficRegulation\Domain\Event\VehicleHasEnteredFacility;
use App\TraficRegulation\Domain\Event\VehicleWasRegistered;
use App\TraficRegulation\Domain\Model\VehicleFleetRepository;
use App\TraficRegulation\Domain\ProcessManager\DefineVehicleDestination;
use App\TraficRegulation\Domain\ProcessManager\PlanVehicleRoute;
use App\TraficRegulation\Infrastructure\InMemoryVehicleFleetRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use App\Simulation\Application\Service\StaticSimulator;
use App\Simulation\Domain\Service\Simulator;
use App\Console\LogDomainEventToConsoleDecorator;
use App\ServiceBus\SimpleEventBus;

return function(ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set(CargoRepository::class, InMemoryCargoRepository::class)
             ->args([ref(EventBus::class)]);

    $services->set(VehicleFleetRepository::class, InMemoryVehicleFleetRepository::class)
             ->args([ref(EventBus::class)]);

    $services->set(CargoHandler::class)
             ->args([ref(CommandBus::class)]);

    $services->set(RegisterCargoInTheFacilityHandler::class)
             ->args([ref(CargoRepository::class)]);

    $services->set(LoadCargoHandler::class)
             ->args([ref(CargoRepository::class)]);

    $services->set(UnloadCargoHandler::class)
             ->args([ref(CargoRepository::class)]);

    $services->set(CreateVehicleFleetHandler::class)
             ->args([ref(VehicleFleetRepository::class)]);

    $services->set(AddVehicleHandler::class)
             ->args([ref(VehicleFleetRepository::class)]);

    $services->set(ComputeVehicleRouteHandler::class)
             ->args([ref(VehicleFleetRepository::class)]);

    $services->set(RepositionVehicleFleetHandler::class)
             ->args([ref(VehicleFleetRepository::class)]);

    $services->set(PlanVehicleRoute::class)
             ->args([ref(CommandBus::class)]);

    $services->set(Simulator::class, StaticSimulator::class)
             ->args([ref(CommandBus::class)]);

    $services->set(CommandBus::class)
             ->args([ref('app.command_handler_locator')]);

    $services->set('app.command_handler_locator', ServiceLocator::class)
             ->args([[
                 // Vehicle Fleet Aggregate
                 CreateVehicleFleet::class => ref(CreateVehicleFleetHandler::class),
                 AddVehicle::class => ref(AddVehicleHandler::class),
                 ComputeVehicleRoute::class => ref(ComputeVehicleRouteHandler::class),
                 RepositionVehicleFleet::class => ref(RepositionVehicleFleetHandler::class),

                 // Cargo Aggregate
                 RegisterCargoInTheFacility::class => ref(RegisterCargoInTheFacilityHandler::class),
                 LoadCargo::class => ref(LoadCargoHandler::class),
                 UnloadCargo::class => ref(UnloadCargoHandler::class),
             ]])
             ->tag('container.service_locator');

    $services->set(EventBus::class, SimpleEventBus::class)
             ->args([[
                 VehicleHasBeenAdded::class => [
                     [ ref(CargoHandler::class), 'onVehicleHasBeenAdded' ],
                     [ ref(PlanVehicleRoute::class), 'onVehicleHasBeenAdded' ],
                 ],
                 VehicleHasEnteredFacility::class => [
                     [ ref(CargoHandler::class), 'onVehicleHasEnteredFacility' ],
                 ],
                 CargoWasRegistered::class => [
                     [ ref(CargoHandler::class), 'onCargoWasRegistered' ],
                     [ ref(PlanVehicleRoute::class), 'onCargoWasRegistered' ],
                     [ ref(Simulator::class), 'onCargoWasRegistered' ],
                 ],
                 CargoWasLoaded::class => [
                     [ ref(CargoHandler::class), 'onCargoWasLoaded' ],
                     [ ref(PlanVehicleRoute::class), 'onCargoWasLoaded' ],
                 ],
                 CargoWasUnloaded::class => [
                     [ ref(CargoHandler::class), 'onCargoWasUnloaded' ],
                     [ ref(PlanVehicleRoute::class), 'onCargoWasUnloaded' ],
                     [ ref(Simulator::class), 'onCargoWasUnloaded' ],
                 ],
             ]]);

    $services->set(LogDomainEventToConsoleDecorator::class)
        ->decorate(EventBus::class)
        ->args([ref(LogDomainEventToConsoleDecorator::class.'.inner')]);
};
