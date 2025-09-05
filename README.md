"# TempTableBundle" 

#Exemple

'''
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
        return Command::FAILURE;
    }
}
    '''