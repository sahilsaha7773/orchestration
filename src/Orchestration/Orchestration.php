<?php

namespace Utopia\Orchestration;

use Utopia\Orchestration\Adapter;
use Exception;
use Utopia\Orchestration\Exception\Orchestration as ExceptionOrchestration;

class Orchestration
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Command Line String into Array
     * 
     * This function will convert a string containing a command into an array of arguments.
     * It will go through the string and find all instances of spaces, and will split the string
     * however if it detects a apostrophe comes after the space it will find the next apostrophe and split the entire thing
     * and add it to the array. This is so arguments with spaces in them can be passed such as scripts for sh or bash.
     * 
     * If there are no spaces detected in the first place it will just return the string as an array.
     *
     * @param string $command
     *
     * @return (false|string)[]
     */
    public function parseCommandString(string $command): array 
    {
        $currentPos = 0;
        $commandProcessed = [];

        if (strpos($command, ' ', $currentPos) === false) {
            return [$command];
        }
        
        while (true) {
            if (strpos($command, ' ', $currentPos) !== false) {
                $place = (int) strpos($command, ' ', $currentPos);
    
                if ($command[$place + 1] !== "'") {
                    array_push($commandProcessed, substr($command, $currentPos, $place - $currentPos));
                    $place = $place + 1;
                } else {
                    array_push($commandProcessed, substr($command, $currentPos, $place - $currentPos));
                    
    
                    $closingString = strpos($command, "'", $place + 2);

                    if ($closingString == false) {
                        throw new Exception("Invalid Command given, are you missing an `'` at the end?");
                    }

                    array_push($commandProcessed, substr($command, $place + 1, $closingString));
                    $place = $closingString + 1;
                }
                
                if (strpos($command, ' ', $place) === false) {
                   if (!empty(substr($command, $place, strlen($command) - $currentPos))) {
                        array_push($commandProcessed, substr($command, $place, strlen($command) - $currentPos));
                    }
                }
                
                $currentPos = $place;
            } else {
                break;
            }
        }

        return $commandProcessed;
    }


    /**
     * Create Network
     * 
     * @param string $name
     * @param bool $internal
     * 
     * @return bool
     */
    public function createNetwork(string $name, bool $internal = false): bool 
    {
        return $this->adapter->createNetwork($name, $internal);
    }

    /**
     * Remove Network
     * 
     * @param string $name
     * 
     * @return bool
     */
    public function removeNetwork(string $name): bool 
    {
        return $this->adapter->removeNetwork($name);
    }

    /**
     * List Networks
     * 
     * @return array
     */
    public function listNetworks(): array 
    {
        return $this->adapter->listNetworks();
    }

    /**
     * Connect a container to a network
     * 
     * @param string $container
     * @param string $network
     * 
     * @return bool
     */
    public function networkConnect(string $container, string $network): bool 
    {
        return $this->adapter->networkConnect($container, $network);
    }

    /**
     * Get usage stats of containers
     * 
     * @param string $container
     * @param array<string, string> $filters
     * @param int $cycles
     * 
     * @return array
     */
    public function getStats(string $container = null, array $filters = [], int $cycles = 1): array 
    {
        $averageStats = [];

        for ($i = 0; $i < $cycles; $i++) {
            $averageStats[] = $this->adapter->getStats($container, $filters);
        }

        // If no cycles, return empty
        if(\count($averageStats) <= 0) {
            return [];
        }

        // If one cycle, return the cycle
        if(\count($averageStats) <= 1) {
            return $averageStats[0];
        }

        // If multiple cycles, average them
        $containerIds = \array_map(fn($stat) => $stat['id'], $averageStats[0]);
        $response = [];

        foreach ($containerIds as $containerId) {
            $averageCpu = 0;
            $averageMemory = 0;
            $averageDiskIO = 0;
            $averageMemoryIO = 0;
            $averageNetworkIO = 0;
    
            foreach ($averageStats as $statArr) {
                $statIndex = \array_search($containerId, \array_map(fn ($statI) => $statI['id'], $statArr));
                $stat = $statArr[$statIndex] ?? [];
                
                $averageCpu += $stat['cpu'] ?? 0;
                $averageMemory += $stat['memory'] ?? 0;
                $averageDiskIO += $stat['diskIO'] ?? 0;
                $averageMemoryIO += $stat['memoryIO'] ?? 0;
                $averageNetworkIO += $stat['networkIO'] ?? 0;
            }
    
            $statsCount = \count($stat);
    
            $averageCpu /= $statsCount;
            $averageMemory /= $statsCount;
            $averageDiskIO /= $statsCount;
            $averageMemoryIO /= $statsCount;
            $averageNetworkIO /= $statsCount; 

            $response[] = [
                'id' => $averageStats[0]['id'],
                'name' => $averageStats[0]['name'],
                'cpu' => $averageCpu,
                'memory' => $averageMemory,
                'diskIO' => $averageDiskIO,
                'memoryIO' => $averageMemoryIO,
                'networkIO' => $averageNetworkIO
            ];
        }

      

        return $response;
    }

    /**
     * Disconnect a container from a network
     * 
     * @param string $container
     * @param string $network
     * @param bool $force
     * 
     * @return bool
     */
    public function networkDisconnect(string $container, string $network, bool $force = false): bool 
    {
        return $this->adapter->networkDisconnect($container, $network, $force);
    }

    /**
     * Pull Image
     * 
     * @param string $image
     * 
     * @return bool
     */
    public function pull(string $image): bool
    {
        return $this->adapter->pull($image);
    }
        
    /**
     * List Containers
     * @param array<string, string> $filters
     *
     * @return Container[]
     */
    public function list(array $filters = []): array
    {
        return $this->adapter->list($filters);
    }

    /**
     * Run Container
     * 
     * Creates and runs a new container, On success it will return a string containing the container ID.
     * On fail it will throw an exception.
     * 
     * @param string $image
     * @param string $name
     * @param string $entrypoint
     * @param string[] $command
     * @param string $workdir
     * @param string[] $volumes
     * @param array<string, string> $vars
     * @param string $mountFolder
     * @param string $hostname
     * @param bool $remove
     * 
     * @return string
     */
    public function run(
        string $image,
        string $name,
        array $command = [],
        string $entrypoint = '',
        string $workdir = '',
        array $volumes = [],
        array $vars = [],
        string $mountFolder = '',
        array $labels = [],
        string $hostname = '',
        bool $remove = false
    ): string {
        return $this->adapter->run($image, $name, $command, $entrypoint, $workdir, $volumes, $vars, $mountFolder, $labels, $hostname, $remove);
    }

    /**
     * Execute Container
     *
     * @param string $name
     * @param string[] $command
     * @param string &$stdout
     * @param string &$stderr
     * @param array<string, string> $vars
     * @param int $timeout
     * @return bool
     */
    public function execute(
        string $name, 
        array $command, 
        string &$stdout, 
        string &$stderr, 
        array $vars = [], 
        int $timeout = -1
    ): bool {
        return $this->adapter->execute($name, $command, $stdout, $stderr, $vars, $timeout);
    }
    
    /**
     * Remove Container
     *
     * @param string $name
     * @param bool $force
     * @return bool
     */
    public function remove(string $name, bool $force = false): bool
    {
        return $this->adapter->remove($name, $force);
    }

    /**
     * Set containers namespace
     * 
     * @param string $namespace
     * @return $this
     */
    public function setNamespace(string $namespace): self
    {
        $this->adapter->setNamespace($namespace);
        return $this;
    }

    /**
     * Set max allowed CPU cores per container
     * 
     * @param int $cores
     * @return $this
     */
    public function setCpus(int $cores): self
    {
        $this->adapter->setCpus($cores);
        return $this;
    }

    /**
     * Set max allowed memory in mb per container
     * 
     * @param int $mb
     * @return $this
     */
    public function setMemory(int $mb): self
    {
        $this->adapter->setMemory($mb);
        return $this;
    }

    /**
     * Set max allowed swap memory in mb per container
     * 
     * @param int $mb
     * @return $this
     */
    public function setSwap(int $mb): self
    {
        $this->adapter->setSwap($mb);
        return $this;
    }
}