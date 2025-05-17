<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BuildXCScore extends Command
{
    protected $signature = 'xc:build';
    protected $description = 'Compila el binario xc_score desde xc_score.c';

    public function handle()
    {
        $script = base_path('compile_xc_score.sh');

        if (!file_exists($script)) {
            $this->error('Script no encontrado: ' . $script);
            return 1;
        }

        $this->info('Compilando xc_score...');
        exec("sh $script", $output, $return);
        foreach ($output as $line) {
            $this->line($line);
        }

        return $return;
    }
}
