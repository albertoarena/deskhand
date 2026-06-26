<?php

declare(strict_types=1);

use Deskhand\Console\Application;

function skillContents(): string
{
    return (string) file_get_contents(__DIR__.'/../../../skill/SKILL.md');
}

it('has the skill frontmatter', function () {
    expect(skillContents())->toStartWith('---')
        ->and(skillContents())->toContain('name: deskhand')
        ->and(skillContents())->toContain('description:');
});

it('documents every deskhand command', function () {
    $skill = skillContents();

    foreach (['up', 'down', 'list', 'status'] as $command) {
        expect($skill)->toContain("deskhand {$command}");
    }
});

it('documents every option of the up and down commands', function () {
    $application = new Application;
    $skill = skillContents();

    foreach (['up', 'down'] as $name) {
        foreach ($application->find($name)->getDefinition()->getOptions() as $option) {
            expect($skill)->toContain('--'.$option->getName());
        }
    }
});
