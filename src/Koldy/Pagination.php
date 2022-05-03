<?php namespace Koldy;

/**
 * Class Pagination
 *
 * It is recommended to extend this class and setup your own values in protected properties.
 *
 * @deprecated Don't rely on this class any more
 */
class Pagination
{

    /**
     * The total number of items in the data set; so we can know how many pages is there
     * @var int
     */
    protected $maxItems = null;

    /**
     * The current page, by default, we're on first page
     * @var int
     */
    protected $currentPage = null;

    /**
     * How many items will be visible per one page? So we can know how many pages will be there
     * @var int
     */
    protected $itemsPerPage = 10;

    /**
     * The main tag for pagination wrapper. We recommend nav, but you may use div or any other, if you think
     * it's better.
     * @var string
     */
    protected $mainPaginationTag = 'nav';

    /**
     * Text on the link for first page
     * @var string
     */
    protected $firstPageText = '&lArr;';

    /**
     * Text on the "previous page" button
     * @var string
     */
    protected $previousPageText = '&larr;';

    /**
     * Text on the "next page" button
     * @var string
     */
    protected $nextPageText = '&rarr;';

    /**
     * Text on the "last page" button
     * @var string
     */
    protected $lastPageText = '&rArr;';

    /**
     * The CSS class on "first page" button
     * @var string
     */
    protected $firstPageCss = 'first';

    /**
     * The CSS class on "previous page" button
     * @var string
     */
    protected $previousPageCss = 'previous';

    /**
     * The CSS class for "next page" button
     * @var string
     */
    protected $nextPageCss = 'next';

    /**
     * The CSS class for "last page" button
     * @var string
     */
    protected $lastPageCss = 'last';

    /**
     * The CSS class for currently selected page number button
     * @var string
     */
    protected $cssSelected = 'active';

    /**
     * The CSS class for main DIV which holds all other elements
     * @var string
     */
    protected $mainCssClass = 'pagination';

    /**
     * The CSS class on "left" controls
     * @var string
     */
    protected $previousDivCss = '';

    /**
     * The CSS class on "next" button wrapper
     * @var string
     */
    protected $nextDivCss = '';

    /**
     * The CSS class on "pages" control
     * @var string
     */
    protected $pagesDivCss = 'pages';

    /**
     * The CSS class attached to the button/link of page
     * @var string
     */
    protected $pageLinkCss = '';

    /**
     * The CSS class on "first" page div. If empty, div won't be generated.
     * @var string
     */
    protected $firstDivCss = '';

    /**
     * The CSS class on "last" page div. If empty, div won't be generated.
     * @var string
     */
    protected $lastDivCss = '';

    /**
     * The number of page links - if there's total of 30 pages, and you're on page 12, then it means
     * we'll generate links to pages 9, 10, 11, 12, 13, 14 and 15
     * @var int
     */
    protected $numberOfPageLinks = 7;

    /**
     * The URL pattern that will be embedded as {href} parameter
     * @var string
     */
    protected $urlPattern = '?page={page}';

    /**
     * The button's HTML pattern, available variables are:
     * {href} the URL pattern
     * {class} class for the element, depending on other parameters and current case
     * {page} the target page number
     * {text} the button text: the page number of text from other parameters
     * @var string
     */
    protected $buttonHtmlPattern = '<a href="{href}" class="{class}" data-page="{page}">{text}</a>';

    /**
     * Weather to generate markup for first/last buttons or not
     * @var bool
     */
    protected $showFirstLast = true;

    /**
     * If "first" or "last" buttons doesn't need to be shown, then append this CSS class.
     * If it's empty string, the they won't be generated into markup at all.
     * @var string
     */
    protected $hiddenFirstLastCss = '';

    /**
     * Generate markup for "previous" and "next" buttons
     * @var bool
     */
    protected $showPreviousNext = true;

    /**
     * The CSS class appended to "previous" and "next" buttons if they don't need to be shown
     * If it's empty string, the they won't be generated into markup at all.
     * @var string
     */
    protected $hiddenPreviousNextCss = '';

    // other values detected within the class

    /**
     * @var int
     */
    protected $totalPages = null;

    /**
     * @var int
     */
    protected $startPage = null;

    /**
     * @var int
     */
    protected $endPage = null;

    /**
     * @var int
     */
    protected $half = null;


    /**
     * Pagination constructor.
     *
     * @param int $maxItems
     * @param int $currentPage
     * @param int $itemsPerPage
     */
    public function __construct($maxItems, $currentPage = 1, $itemsPerPage = 10)
    {
        $this->maxItems = $maxItems;
        $this->currentPage = $currentPage;
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @param int $numberOfPageLinks
     *
     * @return $this
     */
    public function setLinksPerPage($numberOfPageLinks)
    {
        $this->numberOfPageLinks = $numberOfPageLinks;
        return $this;
    }

    /**
     * @param string $urlPattern
     *
     * @return $this
     */
    public function setLink($urlPattern)
    {
        $this->urlPattern = $urlPattern;
        return $this;
    }

    /**
     * Get the link href
     *
     * @param int $page
     *
     * @return string
     */
    protected function getLinkHref($page)
    {
        return str_replace('{page}', $page, $this->urlPattern);
    }

    /**
     * Get the HTML for complete link
     *
     * @param string $text
     * @param string|int $page
     * @param string $css
     *
     * @return mixed
     */
    protected function getLinkHtml($text, $page, $css)
    {
        $html = $this->buttonHtmlPattern;

        $html = str_replace('{href}', $this->getLinkHref($page), $html);
        $html = str_replace('{text}', $text, $html);
        $html = str_replace('{page}', $page, $html);
        $html = str_replace('{class}', $css, $html);

        if ($css == '') {
            $html = str_replace(' class=""', '', $html);
        }

        return $html;
    }

    /**
     * @return string
     */
    protected function getHtml4FirstPage()
    {
        if ($this->showFirstLast) {
            $html = '';
            $shouldShow = ($this->currentPage > 1);

            if ($shouldShow) {
                $html .= $this->getLinkHtml($this->firstPageText, 1, $this->firstPageCss);
            } else if ($this->hiddenFirstLastCss != '') {
                $html .= $this->getLinkHtml($this->firstPageText, 1, "{$this->firstPageCss} {$this->hiddenFirstLastCss}");
            }

            if ($this->firstDivCss != '') {
                if ($shouldShow) {
                    return "<div class=\"{$this->firstDivCss}\">{$html}</div>";
                } else {
                    return "<div class=\"{$this->firstDivCss} {$this->hiddenFirstLastCss}\">{$html}</div>";
                }
            } else {
                return $html;
            }
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function getHtml4PreviousPage()
    {
        if ($this->showPreviousNext) {
            $html = '';
            $shouldShow = ($this->currentPage > 1);

            if ($shouldShow) {
                $html .= $this->getLinkHtml($this->previousPageText, $this->currentPage - 1, $this->previousPageCss);
            } else if ($this->hiddenPreviousNextCss != '') {
                $html .= $this->getLinkHtml($this->previousPageText, $this->currentPage - 1, "{$this->previousPageCss} {$this->hiddenPreviousNextCss}");
            }

            if ($this->previousDivCss != '') {
                if ($shouldShow) {
                    return "<div class=\"{$this->previousDivCss}\">{$html}</div>";
                } else {
                    return "<div class=\"{$this->previousDivCss} {$this->hiddenPreviousNextCss}\">{$html}</div>";
                }
            } else {
                return $html;
            }
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function getHtml4Pages()
    {
        $html = "<div class=\"{$this->pagesDivCss}\">";

        for ($i = $this->startPage; $i <= $this->endPage; $i++) {
            $css = $this->pageLinkCss;

            if ($i == $this->currentPage) {
                $css .= ' ' . $this->cssSelected;
            }

            $html .= $this->getLinkHtml($i, $i, $css);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * @return string
     */
    protected function getHtml4NextPage()
    {
        if ($this->showPreviousNext) {
            $html = '';
            $shouldShow = ($this->currentPage < $this->totalPages);

            if ($shouldShow) {
                $html .= $this->getLinkHtml($this->nextPageText, $this->currentPage + 1, $this->nextPageCss);
            } else if ($this->hiddenPreviousNextCss != '') {
                $html .= $this->getLinkHtml($this->nextPageText, $this->currentPage + 1, "{$this->nextPageCss} {$this->hiddenPreviousNextCss}");
            }

            if ($this->nextDivCss != '') {
                if ($shouldShow) {
                    return "<div class=\"{$this->nextDivCss}\">{$html}</div>";
                } else {
                    return "<div class=\"{$this->nextDivCss} {$this->hiddenPreviousNextCss}\">{$html}</div>";
                }
            } else {
                return $html;
            }
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function getHtml4LastPage()
    {
        if ($this->showFirstLast) {
            $html = '';
            $shouldShow = ($this->currentPage < $this->totalPages);

            if ($shouldShow) {
                $html .= $this->getLinkHtml($this->lastPageText, $this->totalPages, $this->lastPageCss);
            } else if ($this->hiddenFirstLastCss != '') {
                $html .= $this->getLinkHtml($this->lastPageText, $this->totalPages, "{$this->lastPageCss} {$this->hiddenFirstLastCss}");
            }

            if ($this->lastDivCss != '') {
                if ($shouldShow) {
                    return "<div class=\"{$this->lastDivCss}\">{$html}</div>";
                } else {
                    return "<div class=\"{$this->lastDivCss} {$this->hiddenFirstLastCss}\">{$html}</div>";
                }
            } else {
                return $html;
            }
        } else {
            return '';
        }
    }

    /**
     * Detect other values needed for generate methods
     */
    protected function detectOtherValues()
    {
        $this->totalPages = ceil($this->maxItems / $this->itemsPerPage);

        $this->half = floor($this->numberOfPageLinks / 2);
        $this->startPage = $this->currentPage - $this->half;

        if ($this->startPage < 1) {
            $this->startPage = 1;
        }

        $this->endPage = $this->startPage + $this->numberOfPageLinks - 1;

        if ($this->endPage > $this->totalPages) {
            $this->endPage = $this->totalPages;
        }
    }

    /**
     * Get how many items is there per page
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * Get how many max items is there
     * @return int
     */
    public function getMaxItems()
    {
        return $this->maxItems;
    }

    /**
     * Get the start page number
     * @return int
     */
    public function getStartPage()
    {
        if ($this->startPage == null) {
            $this->detectOtherValues();
        }

        return $this->startPage;
    }

    /**
     * Get the end page number
     * @return int
     */
    public function getEndPage()
    {
        if ($this->endPage == null) {
            $this->detectOtherValues();
        }

        return $this->endPage;
    }

    /**
     * Get how many total pages is there
     * @return int
     */
    public function getTotalPages()
    {
        if ($this->totalPages == null) {
            $this->detectOtherValues();
        }

        return $this->totalPages;
    }

    /**
     * The generate method
     * @return string
     */
    public function toHtml()
    {
        $this->detectOtherValues();

        $html = '';
        $html .= "<{$this->mainPaginationTag} class=\"{$this->mainCssClass}\">";
        $html .= $this->getHtml4FirstPage();
        $html .= $this->getHtml4PreviousPage();
        $html .= $this->getHtml4Pages();
        $html .= $this->getHtml4NextPage();
        $html .= $this->getHtml4LastPage();
        $html .= "</{$this->mainPaginationTag}>";

        return $html;
    }

    public function __toString()
    {
        return $this->toHtml();
    }

}
