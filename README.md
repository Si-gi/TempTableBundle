# TempTableBundle
This bundle aim to manage table database which are not entity
# How to import a csv as Table

```

{
    // autowiring
    public function __construct(private TempTableManager $tempTable,
    private LoggerInterface $logger,
    private Connection $doctrineConnection,
    private TableFactory $tableFactory
    ){
         parent::__construct();
    }

    public function import(){
        $config = new TempTableConfig('temp_', 24, 1000);
        $connection = new DoctrineDatabaseConnection($this->doctrineConnection);
        $typeConverter = new PostgreSqlTypeConverter();

        // services creations
        $tableCreator = new PostgreSqlTableCreator($connection, $this->tableFactory, $config, $this->logger);
        $csvImporter = new StrategyCsvImporter(
            $connection, 
            $this->logger,
            new CopyFromImportStrategy($connection, $this->logger),
            new BatchInsertStrategy($connection, $typeConverter, $config, $this->logger)
        );
        $tableRegistry = new DatabaseTableRegistry($connection, $this->logger);
        $tableCleaner = new ExpiredTableCleaner($connection, $tableRegistry, $config, $this->logger);

        $tempTableManager = new TempTableManager(
            $tableCreator,
            $csvImporter, 
            $tableRegistry,
            $tableCleaner,
            $this->logger
        );
 
        $tempTableManager ->createTableFromCsv(__DIR__."/CsvTest.csv", "test");

```

# How to fetch data using the bundle:
```
    public function fetchData(Connection $doctrineConnection): int
    {

        $query = new TempTableQuery($doctrineConnection);
        $conditions = ['col_453' => "v0nMqky1BP"];
        $query->query("tmptemp_test", ["col_453"])->addConditions($conditions);
        $results =$query->getQb()->executeQuery()->fetchAllAssociative();
        dump($results);
        return Command::FAILURE;
    }
```
