<?php

namespace App\Enums;

enum RelationshipToHead: string
{
    case Head = 'head';
    case Spouse = 'spouse';
    case Child = 'child';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case Grandchild = 'grandchild';
    case Grandparent = 'grandparent';
    case InLaw = 'in_law';
    case OtherRelative = 'other_relative';
    case NonRelative = 'non_relative';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head of household',
            self::Spouse => 'Spouse / Partner',
            self::Child => 'Son / Daughter',
            self::Parent => 'Parent',
            self::Sibling => 'Brother / Sister',
            self::Grandchild => 'Grandchild',
            self::Grandparent => 'Grandparent',
            self::InLaw => 'In-law',
            self::OtherRelative => 'Other relative',
            self::NonRelative => 'Non-relative / Boarder',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
