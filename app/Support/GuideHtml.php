<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Rebuild a small editorial vocabulary; never render uploaded HTML verbatim. */
final class GuideHtml
{
    private const TAGS = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'strong', 'em', 'b', 'i', 'u',
        'a', 'br', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'code'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript',
        'form', 'input', 'button', 'textarea', 'select', 'audio', 'video', 'canvas', 'head', 'link', 'meta'];

    public function clean(?string $html): string
    {
        if (!$html) return '';
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            return $body ? trim($this->children($body)) : '';
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function children(DOMNode $parent): string
    {
        $result = '';
        foreach ($parent->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $result .= htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                continue;
            }
            if (!$node instanceof DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP, true)) continue;
            $content = $this->children($node);
            if (!in_array($tag, self::TAGS, true)) {
                $result .= in_array($tag, ['div', 'section', 'article'], true) ? "\n".$content."\n" : $content;
                continue;
            }
            $attributes = '';
            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                // No relative, protocol-relative, credentials, data, control characters or executable schemes.
                if (filter_var($href, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $href)
                    && !preg_match('/[\x00-\x20\x7f]/', $href) && !parse_url($href, PHP_URL_USER)) {
                    $attributes = ' href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" rel="noopener noreferrer"';
                }
            }
            if (in_array($tag, ['td', 'th'], true)) foreach (['colspan', 'rowspan'] as $span) {
                if (preg_match('/^[1-9][0-9]?$/D', $node->getAttribute($span))) $attributes .= ' '.$span.'="'.$node->getAttribute($span).'"';
            }
            $result .= '<'.$tag.$attributes.'>'.$content.(in_array($tag, ['br', 'hr'], true) ? '' : '</'.$tag.'>');
        }
        return $result;
    }

    public function text(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($this->clean($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
