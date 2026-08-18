<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedTestError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* themes/custom/b5_dcg/templates/node/node--resident_notes.html.twig */
class __TwigTemplate_c5ee36f95f3603a1332307ca36719194 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(Twig\Extension\CoreExtension::include($this->env, $context, "@b5_dcg/includes/resident_notes_node_header.twig"));
        yield "
<div class=\"container mt-4\">
  <div class=\"row fs-4 mb-2\">
    <div class=\"col\">
      <span class=\"fw-bold\">Date: </span>";
        // line 5
        yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_arrival", [], "any", false, false, true, 5), "date", [], "any", false, false, true, 5), "Y-m-d"), "html", null, true);
        yield "
    </div>
    <div class=\"col\">
    ";
        // line 8
        if ((CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_site_location", [], "any", false, false, true, 8), "entity", [], "any", false, false, true, 8), "title", [], "any", false, false, true, 8), "value", [], "any", false, false, true, 8) == "~Other...")) {
            // line 9
            yield "      <span class=\"fw-bold\">Site: </span>";
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_site_location_other", [], "any", false, false, true, 9), "value", [], "any", false, false, true, 9), "html", null, true);
            yield "<br />
      <span class=\"fw-bold\">Address: </span>";
            // line 10
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_resident_address", [], "any", false, false, true, 10), "value", [], "any", false, false, true, 10), "html", null, true);
            yield "
    ";
        } else {
            // line 12
            yield "      <span class=\"fw-bold\">Site: </span>";
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_site_location", [], "any", false, false, true, 12), "entity", [], "any", false, false, true, 12), "title", [], "any", false, false, true, 12), "value", [], "any", false, false, true, 12), "html", null, true);
            yield "<br />
      <span class=\"fw-bold\">Address: </span>";
            // line 13
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_resident_address", [], "any", false, false, true, 13), "value", [], "any", false, false, true, 13), "html", null, true);
            yield "
    ";
        }
        // line 15
        yield "    </div>
  </div>
  <div class=\"row\">
    <div class=\"col\">
      <div class=\"fs-4\"><span class=\"fw-bold\">Notes: </span></div>
      ";
        // line 20
        yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_description", [], "any", false, false, true, 20), "value", [], "any", false, false, true, 20));
        yield "
    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["node"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/custom/b5_dcg/templates/node/node--resident_notes.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  86 => 20,  79 => 15,  74 => 13,  69 => 12,  64 => 10,  59 => 9,  57 => 8,  51 => 5,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/b5_dcg/templates/node/node--resident_notes.html.twig", "/var/www/html/web/themes/custom/b5_dcg/templates/node/node--resident_notes.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 8];
        static $filters = ["escape" => 5, "date" => 5, "raw" => 20];
        static $functions = ["include" => 1];
        static $tests = [];

        try {
            $this->sandbox->checkSecurity(
                [0 => "if"],
                [0 => "escape", 1 => "date", 2 => "raw"],
                [0 => "include"],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            } elseif ($e instanceof SecurityNotAllowedTestError && isset($tests[$e->getTestName()])) {
                $e->setTemplateLine($tests[$e->getTestName()]);
            }

            throw $e;
        }

    }
}
