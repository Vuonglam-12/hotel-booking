param([string]$Name = 'invoice')
$ErrorActionPreference = 'Stop'
if ($Name -notin @('invoice', 'invoice-long')) { throw 'Only the synthetic document previews are supported.' }
$preview = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../../storage/app/private/aurel-document-preview')).Path
Add-Type -AssemblyName System.Runtime.WindowsRuntime
[Windows.Storage.StorageFile, Windows.Storage, ContentType=WindowsRuntime] | Out-Null
[Windows.Data.Pdf.PdfDocument, Windows.Data.Pdf, ContentType=WindowsRuntime] | Out-Null
[Windows.Storage.Streams.InMemoryRandomAccessStream, Windows.Storage.Streams, ContentType=WindowsRuntime] | Out-Null
[Windows.Storage.Streams.DataReader, Windows.Storage.Streams, ContentType=WindowsRuntime] | Out-Null
[Windows.Data.Pdf.PdfPageRenderOptions, Windows.Data.Pdf, ContentType=WindowsRuntime] | Out-Null
$genericTask = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object {
    $_.Name -eq 'AsTask' -and $_.IsGenericMethod -and $_.GetGenericArguments().Count -eq 1 -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
} | Select-Object -First 1
$actionTask = [System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object {
    $_.Name -eq 'AsTask' -and -not $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction'
} | Select-Object -First 1
function Wait-Result($Operation, [Type]$ResultType) {
    $task = $genericTask.MakeGenericMethod($ResultType).Invoke($null, @($Operation))
    $task.GetAwaiter().GetResult()
}
$file = Wait-Result ([Windows.Storage.StorageFile]::GetFileFromPathAsync((Join-Path $preview ($Name + '.pdf')))) ([Windows.Storage.StorageFile])
$document = Wait-Result ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
for ($index = 0; $index -lt $document.PageCount; $index++) {
    $page = $document.GetPage($index)
    $stream = New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
    try {
        $options = New-Object Windows.Data.Pdf.PdfPageRenderOptions
        $options.DestinationWidth = 1240
        $renderTask = $actionTask.Invoke($null, @($page.RenderToStreamAsync($stream, $options)))
        [void]$renderTask.GetAwaiter().GetResult()
        $reader = New-Object Windows.Storage.Streams.DataReader($stream.GetInputStreamAt(0))
        try {
            [void](Wait-Result ($reader.LoadAsync([uint32]$stream.Size)) ([uint32]))
            $bytes = New-Object byte[] ([int]$stream.Size)
            $reader.ReadBytes($bytes)
            [IO.File]::WriteAllBytes((Join-Path $preview ($Name + '-page-' + ($index + 1) + '.png')), $bytes)
        } finally { $reader.Dispose() }
    } finally { $page.Dispose(); $stream.Dispose() }
}
Write-Output ($Name + ': rendered ' + $document.PageCount + ' PDF pages to PNG')
