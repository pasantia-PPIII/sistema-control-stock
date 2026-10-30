<?php
/**
 * clases/pdf_reporte.php
 * Generador de PDF mínimo, en PHP puro y sin librerías externas, para exportar los reportes (RF-41).
 *
 * Alcanza para lo que necesitan los reportes: título, secciones con una nota, tablas con encabezado que se repite
 * en cada hoja, texto que se parte en varias líneas y pie con numeración. Usa las fuentes estándar Helvetica
 * (no se incrustan) con codificación Windows-1252, así que admite tildes y la ñ.
 *
 * Uso:
 *   $pdf = new PdfReporte('Reporte de stock', 'Generado por ... el 29/10/2026');
 *   $pdf->seccion('Stock crítico', 'Materiales en o bajo el mínimo.');
 *   $pdf->tabla(['Código', 'Material', 'Stock'], $filas, [1, 3, 1], ['', '', 'R']);
 *   $pdf->descargar('reporte-stock.pdf');
 */
class PdfReporte
{
    // Hoja A4 apaisada, en puntos (1 pt = 1/72 pulgada)
    private const ANCHO = 841.89;
    private const ALTO = 595.28;
    private const MARGEN = 36.0;
    private const ALTO_PIE = 24.0;

    // Anchos de los caracteres de Helvetica (milésimas de em) para el código ASCII 32 a 126
    private const ANCHOS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];
    // La negrita es algo más ancha: se usa un factor en lugar de una segunda tabla
    private const FACTOR_NEGRITA = 1.07;

    private string $titulo;
    private string $subtitulo;
    /** @var string[] Contenido (operadores PDF) de cada hoja, sin el pie */
    private array $paginas = [];
    private int $actual = -1;
    private float $y = 0.0;
    /** Encabezado de la tabla en curso, para repetirlo al saltar de hoja */
    private ?array $encabezadoTabla = null;

    public function __construct(string $titulo, string $subtitulo = '')
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->nuevaPagina();
    }

    // ---------------------------------------------------------------------------------------------
    // API pública
    // ---------------------------------------------------------------------------------------------

    /** Título de una sección y, opcionalmente, una nota explicativa debajo. */
    public function seccion(string $titulo, string $nota = ''): void
    {
        $this->asegurarEspacio(60);
        $this->y += 8;
        $this->texto(self::MARGEN, $this->y + 11, $titulo, 11, true);
        $this->y += 16;
        if ($nota !== '') {
            foreach ($this->partir($nota, 8, false, self::ANCHO - 2 * self::MARGEN) as $linea) {
                $this->asegurarEspacio(12);
                $this->texto(self::MARGEN, $this->y + 8, $linea, 8, false, [0.35, 0.35, 0.35]);
                $this->y += 11;
            }
        }
        $this->y += 4;
    }

    /** Línea de texto simple (por ejemplo "Total: 12"). */
    public function linea(string $texto, bool $negrita = false): void
    {
        foreach ($this->partir($texto, 9, $negrita, self::ANCHO - 2 * self::MARGEN) as $linea) {
            $this->asegurarEspacio(14);
            $this->texto(self::MARGEN, $this->y + 9, $linea, 9, $negrita);
            $this->y += 12;
        }
    }

    /**
     * Tabla con encabezado que se repite en cada hoja.
     * @param string[] $cabeceras Títulos de las columnas
     * @param array[] $filas Filas (cada una, un valor por columna; se convierten a texto)
     * @param float[] $pesos Ancho relativo de cada columna (se reparten el ancho útil de la hoja)
     * @param string[] $alineacion 'R' alinea a la derecha; cualquier otro valor, a la izquierda
     * @param string $vacio Texto cuando no hay filas
     */
    public function tabla(array $cabeceras, array $filas, array $pesos = [], array $alineacion = [], string $vacio = 'Sin datos para mostrar.'): void
    {
        $n = count($cabeceras);
        $pesos = $pesos + array_fill(0, $n, 1);
        $suma = array_sum(array_slice($pesos, 0, $n)) ?: 1;
        $util = self::ANCHO - 2 * self::MARGEN;
        $anchos = [];
        for ($i = 0; $i < $n; $i++) {
            $anchos[$i] = $util * $pesos[$i] / $suma;
        }
        $this->encabezadoTabla = ['cabeceras' => $cabeceras, 'anchos' => $anchos, 'alineacion' => $alineacion];

        $this->asegurarEspacio(40);
        $this->filaEncabezado();

        if (empty($filas)) {
            $this->asegurarEspacio(20);
            $this->texto(self::MARGEN + 4, $this->y + 13, $vacio, 8.5, false, [0.4, 0.4, 0.4]);
            $this->y += 20;
            $this->encabezadoTabla = null;
            return;
        }

        $cebra = false;
        foreach ($filas as $fila) {
            $celdas = [];
            $maxLineas = 1;
            for ($i = 0; $i < $n; $i++) {
                $lineas = $this->partir((string) ($fila[$i] ?? ''), 8, false, $anchos[$i] - 8);
                $celdas[$i] = $lineas;
                $maxLineas = max($maxLineas, count($lineas));
            }
            $alto = $maxLineas * 10 + 6;

            if ($this->y + $alto > $this->limiteInferior()) {
                $this->nuevaPagina();
                $this->filaEncabezado();
                $cebra = false;
            }

            if ($cebra) {
                $this->rectangulo(self::MARGEN, $this->y, $util, $alto, [0.96, 0.97, 0.98]);
            }
            $x = self::MARGEN;
            for ($i = 0; $i < $n; $i++) {
                foreach ($celdas[$i] as $k => $linea) {
                    $this->textoEnCelda($x, $this->y + 3 + 8 + $k * 10, $anchos[$i], $linea, 8, false, ($alineacion[$i] ?? '') === 'R');
                }
                $x += $anchos[$i];
            }
            $this->y += $alto;
            $this->lineaHorizontal($this->y, [0.88, 0.9, 0.93]);
            $cebra = !$cebra;
        }
        $this->y += 6;
        $this->encabezadoTabla = null;
    }

    /** Envía el PDF al navegador como descarga y termina la ejecución. */
    public function descargar(string $nombreArchivo): void
    {
        $binario = $this->generar();
        $nombreArchivo = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $nombreArchivo);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($binario));
        header('Cache-Control: private, no-store');
        echo $binario;
        exit;
    }

    /** Arma el archivo PDF completo y lo devuelve como cadena binaria. */
    public function generar(): string
    {
        $total = count($this->paginas);
        $objetos = [];                       // número de objeto => contenido
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $hijos = [];
        foreach ($this->paginas as $i => $contenido) {
            $idPagina = 5 + $i * 2;
            $idContenido = $idPagina + 1;
            $hijos[] = $idPagina . ' 0 R';

            $flujo = $contenido . $this->pie($i + 1, $total);
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $idContenido . ' 0 R >>';
            $objetos[$idContenido] = '<< /Length ' . strlen($flujo) . " >>\nstream\n" . $flujo . "\nendstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $hijos) . '] /Count ' . $total . ' >>';
        ksort($objetos);

        $salida = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($salida);
            $salida .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }
        $inicioXref = strlen($salida);
        $maximo = max(array_keys($objetos));
        $salida .= "xref\n0 " . ($maximo + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maximo; $id++) {
            $salida .= sprintf("%010d 00000 n \n", $posiciones[$id]);
        }
        $salida .= "trailer\n<< /Size " . ($maximo + 1) . " /Root 1 0 R >>\nstartxref\n" . $inicioXref . "\n%%EOF\n";
        return $salida;
    }

    // ---------------------------------------------------------------------------------------------
    // Páginas y dibujo
    // ---------------------------------------------------------------------------------------------

    private function limiteInferior(): float
    {
        return self::ALTO - self::MARGEN - self::ALTO_PIE;
    }

    private function nuevaPagina(): void
    {
        $this->paginas[] = '';
        $this->actual = count($this->paginas) - 1;
        $this->y = self::MARGEN;

        $this->texto(self::MARGEN, $this->y + 14, $this->titulo, 15, true);
        $this->y += 20;
        if ($this->subtitulo !== '') {
            $this->texto(self::MARGEN, $this->y + 9, $this->subtitulo, 8.5, false, [0.35, 0.35, 0.35]);
            $this->y += 13;
        }
        $this->lineaHorizontal($this->y + 2, [0.2, 0.25, 0.4], 1.0);
        $this->y += 8;
    }

    /** Salta de hoja si lo que sigue no entra en el espacio que queda. */
    private function asegurarEspacio(float $alto): void
    {
        if ($this->y + $alto > $this->limiteInferior()) {
            $this->nuevaPagina();
        }
    }

    private function filaEncabezado(): void
    {
        $enc = $this->encabezadoTabla;
        $util = self::ANCHO - 2 * self::MARGEN;
        $this->rectangulo(self::MARGEN, $this->y, $util, 18, [0.88, 0.91, 0.96]);
        $x = self::MARGEN;
        foreach ($enc['cabeceras'] as $i => $cabecera) {
            $this->textoEnCelda($x, $this->y + 12.5, $enc['anchos'][$i], $this->recortar($cabecera, 8.5, true, $enc['anchos'][$i] - 8), 8.5, true, ($enc['alineacion'][$i] ?? '') === 'R');
            $x += $enc['anchos'][$i];
        }
        $this->y += 18;
        $this->lineaHorizontal($this->y, [0.6, 0.65, 0.75]);
    }

    private function pie(int $numero, int $total): string
    {
        $anterior = $this->actual;
        $copia = $this->paginas;
        // El pie se arma aparte para no mezclarlo con el contenido ya guardado
        $this->paginas = [''];
        $this->actual = 0;
        $y = self::ALTO - self::MARGEN + 6;
        $this->lineaHorizontal(self::ALTO - self::MARGEN - 8, [0.75, 0.78, 0.85]);
        $this->texto(self::MARGEN, $y, 'Sistema de Control de Stock - Taller de Mantenimiento', 7.5, false, [0.45, 0.45, 0.45]);
        $derecha = "Página {$numero} de {$total}";
        $ancho = $this->anchoTexto($derecha, 7.5, false);
        $this->texto(self::ANCHO - self::MARGEN - $ancho, $y, $derecha, 7.5, false, [0.45, 0.45, 0.45]);
        $contenido = $this->paginas[0];
        $this->paginas = $copia;
        $this->actual = $anterior;
        return $contenido;
    }

    /** Texto en la posición x, y (y medida desde el borde superior de la hoja). */
    private function texto(float $x, float $y, string $texto, float $tam, bool $negrita = false, array $color = [0, 0, 0]): void
    {
        $this->paginas[$this->actual] .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n",
            $negrita ? 'F2' : 'F1', $tam, $color[0], $color[1], $color[2],
            $x, self::ALTO - $y, $this->escapar($texto)
        );
    }

    /** Texto dentro de una celda de ancho $ancho que empieza en $x; alineado a la izquierda o a la derecha. */
    private function textoEnCelda(float $x, float $y, float $ancho, string $texto, float $tam, bool $negrita, bool $derecha): void
    {
        if ($derecha) {
            $x = $x + $ancho - 4 - $this->anchoTexto($texto, $tam, $negrita);
        } else {
            $x += 4;
        }
        $this->texto($x, $y, $texto, $tam, $negrita);
    }

    private function rectangulo(float $x, float $y, float $ancho, float $alto, array $color): void
    {
        $this->paginas[$this->actual] .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $color[0], $color[1], $color[2], $x, self::ALTO - $y - $alto, $ancho, $alto
        );
    }

    private function lineaHorizontal(float $y, array $color, float $grosor = 0.5): void
    {
        $this->paginas[$this->actual] .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $color[0], $color[1], $color[2], $grosor,
            self::MARGEN, self::ALTO - $y, self::ANCHO - self::MARGEN, self::ALTO - $y
        );
    }

    // ---------------------------------------------------------------------------------------------
    // Texto: medición, corte en líneas y codificación
    // ---------------------------------------------------------------------------------------------

    /** Ancho aproximado, en puntos, de un texto UTF-8 con Helvetica (las letras con tilde miden como la base). */
    private function anchoTexto(string $texto, float $tam, bool $negrita): float
    {
        $suma = 0;
        foreach (preg_split('//u', $this->sinTildes($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
            $o = strlen($c) === 1 ? ord($c) : 0;
            $suma += ($o >= 32 && $o <= 126) ? self::ANCHOS[$o - 32] : 556;
        }
        return $suma / 1000 * $tam * ($negrita ? self::FACTOR_NEGRITA : 1);
    }

    private function sinTildes(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }

    /** Parte el texto en líneas que entren en $ancho (por palabras; una palabra muy larga se corta). */
    private function partir(string $texto, float $tam, bool $negrita, float $ancho): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }
        $lineas = [];
        $actual = '';
        foreach (explode(' ', $texto) as $palabra) {
            // Palabra más ancha que la celda: se corta por caracteres
            while ($this->anchoTexto($palabra, $tam, $negrita) > $ancho && mb_strlen($palabra, 'UTF-8') > 1) {
                $trozo = '';
                foreach (preg_split('//u', $palabra, -1, PREG_SPLIT_NO_EMPTY) as $c) {
                    if ($this->anchoTexto($trozo . $c, $tam, $negrita) > $ancho) {
                        break;
                    }
                    $trozo .= $c;
                }
                if ($trozo === '') {
                    break;
                }
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual = '';
                }
                $lineas[] = $trozo;
                $palabra = mb_substr($palabra, mb_strlen($trozo, 'UTF-8'), null, 'UTF-8');
            }
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if ($actual !== '' && $this->anchoTexto($prueba, $tam, $negrita) > $ancho) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /** Recorta con "..." un texto que no entra en una sola línea (encabezados de columna). */
    private function recortar(string $texto, float $tam, bool $negrita, float $ancho): string
    {
        if ($this->anchoTexto($texto, $tam, $negrita) <= $ancho) {
            return $texto;
        }
        while ($texto !== '' && $this->anchoTexto($texto . '...', $tam, $negrita) > $ancho) {
            $texto = mb_substr($texto, 0, -1, 'UTF-8');
        }
        return $texto . '...';
    }

    /** Convierte a Windows-1252 y escapa los caracteres que tienen significado en una cadena PDF. */
    private function escapar(string $texto): string
    {
        $texto = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x80\xA6", "\xC2\xB7"], ['-', '-', '...', '-'], $texto);
        $cp1252 = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($texto, 'Windows-1252', 'UTF-8')
            : (string) iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $texto);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $cp1252);
    }
}
