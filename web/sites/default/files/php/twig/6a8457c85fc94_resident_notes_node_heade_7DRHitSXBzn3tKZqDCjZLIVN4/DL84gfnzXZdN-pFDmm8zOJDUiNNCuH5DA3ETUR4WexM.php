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

/* @b5_dcg/includes/resident_notes_node_header.twig */
class __TwigTemplate_d2961cb2fa20dc619bc5a31e335b6e3c extends Template
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
        yield "<div class=\"container mb-4 mt-4\">
  <div class=\"row\">
    <div class=\"col-12\">
      <h4>Resident Note</h4>
    </div>
  </div>
  <div class=\"row justify-content-between\">
    <div class=\"col-4\">
      <h2>";
        // line 9
        yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "field_resident_name", [], "any", false, false, true, 9), "value", [], "any", false, false, true, 9), "html", null, true);
        yield "</h2>
    </div>
    <div class=\"col-8 text-end\">
      ";
        // line 12
        if ((($tmp = ($context["logged_in"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 13
            yield "      <div class=\"btn-group btn-group-sm\" role=\"group\" aria-label=\"Resident Notes Actions\">
        <a class=\"btn btn-success btn-small me-2\" href=\"/node/add/resident_notes\" role=\"button\">New Note</a>
        <a class=\"btn btn-secondary btn-small\" href=\"/node/";
            // line 15
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "nid", [], "any", false, false, true, 15), "value", [], "any", false, false, true, 15), "html", null, true);
            yield "/edit\" role=\"button\">Edit Note</a>
        <a class=\"btn btn-primary btn-small me-2\" href=\"/resident-notes\" role=\"button\">List Notes</a>
        <a class=\"btn btn-danger btn-small\" href=\"/node/";
            // line 17
            yield (string) $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "nid", [], "any", false, false, true, 17), "value", [], "any", false, false, true, 17), "html", null, true);
            yield "/delete\" role=\"button\">Delete Note</a>
      </div>
      ";
        }
        // line 20
        yield "    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["node", "logged_in"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@b5_dcg/includes/resident_notes_node_header.twig";
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
        return array (  77 => 20,  71 => 17,  66 => 15,  62 => 13,  60 => 12,  54 => 9,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@b5_dcg/includes/resident_notes_node_header.twig", "/var/www/html/web/themes/custom/b5_dcg/templates/includes/resident_notes_node_header.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 12];
        static $filters = ["escape" => 9];
        static $functions = [];
        static $tests = [];

        try {
            $this->sandbox->checkSecurity(
                [0 => "if"],
                [0 => "escape"],
                [],
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
